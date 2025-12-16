<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Order;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\DTOs\CreateLimitOrderDTO;
use App\Models\User;
use App\Events\OrderMatched;
use App\Events\OrderCreated;
use App\Events\OrderCancelled;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct()
    {
    }

    public function getOpenOrders(?string $symbol=null): Collection
    {
        $query = Order::open();
        if($symbol) {
            $query->where('symbol', $symbol);
        }
        return $query->get();
    }

    public function createLimitOrder(CreateLimitOrderDTO $dto): Order
    {
        if($dto->side === OrderSide::BUY) {
            return $this->buyOrder($dto);
        }

        if($dto->side === OrderSide::SELL) {
            return $this->sellOrder($dto);
        }

        throw new \Exception('Invalid order side');
    }

    private function buyOrder(CreateLimitOrderDTO $dto): Order
    {
        return DB::transaction(function () use ($dto) {
            $user = User::lockForUpdate()->find($dto->user->id);
            if (!$user) {
                throw new \Exception('User not found');
            }

            $assetPrice = $dto->price * $dto->amount;
            if ($user->balance < $assetPrice) {
                throw new \Exception('Insufficient balance');
            }

            $user->balance -= $assetPrice;
            $user->save();

            $buyOrder = Order::create($dto->toArray() + ['status' => OrderStatus::OPEN]);

            event(new OrderCreated($buyOrder));

            $matchingSellOrder = Order::open()
                ->where('symbol', $dto->symbol)
                ->where('side', OrderSide::SELL)
                ->where('price', '<=', $dto->price)
                ->orderBy('price', 'asc')
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->first();

            if ($matchingSellOrder) {
                try {
                    $matcher = app(OrderMatchingService::class, [
                        'buyOrder' => $buyOrder,
                        'sellOrder' => $matchingSellOrder,
                    ]);
                    $matcher->execute();
                    $buyOrder->refresh();
                } catch (\Exception $e) {
                    logger()->error('Failed to fill buy order', [
                        'error' => $e->getMessage(),
                        'buy_order_id' => $buyOrder->id,
                        'sell_order_id' => $matchingSellOrder->id,
                    ]);
                }
            }

            return $buyOrder;
        });
    }

    private function sellOrder(CreateLimitOrderDTO $dto): Order
    {
        return DB::transaction(function () use ($dto) {
            $asset = Asset::query()
                ->where('user_id', $dto->user->id)
                ->where('symbol', $dto->symbol)
                ->lockForUpdate()
                ->first();

            if (!$asset) {
                throw new \Exception('Asset not found');
            }

            $availableAmount = $asset->amount - $asset->locked_amount;
            if ($availableAmount < $dto->amount) {
                throw new \Exception('Insufficient assets');
            }

            $asset->amount -= $dto->amount;
            $asset->locked_amount += $dto->amount;
            $asset->save();

            $sellOrder = Order::create($dto->toArray() + ['status' => OrderStatus::OPEN]);

            event(new OrderCreated($sellOrder));

            $matchingBuyOrder = Order::open()
                ->where('symbol', $dto->symbol)
                ->where('side', OrderSide::BUY)
                ->where('price', '>=', $dto->price)
                ->orderBy('price', 'desc')
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->first();

            if ($matchingBuyOrder) {
                try {
                    $matcher = app(OrderMatchingService::class, [
                        'buyOrder' => $matchingBuyOrder,
                        'sellOrder' => $sellOrder,
                    ]);
                    $matcher->execute();
                    $sellOrder->refresh();
                } catch (\Exception $e) {
                    logger()->error('Failed to fill sell order', [
                        'error' => $e->getMessage(),
                        'buy_order_id' => $matchingBuyOrder->id,
                        'sell_order_id' => $sellOrder->id,
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            return $sellOrder;
        });
    }

    public function cancelOrder(string $id): Order
    {
       return DB::transaction(function () use ($id) {
            $order = Order::lockForUpdate()->find($id);
            if(!$order) {
                throw new \Exception('Order not found');
            }

            if(in_array($order->status, [OrderStatus::FILLED, OrderStatus::CANCELLED])) {
                throw new \Exception('Order cannot be cancelled');
            }
            $order->status = OrderStatus::CANCELLED;
            $order->save();

            event(new OrderCancelled($order));

            if($order->side === OrderSide::BUY) {
                $buyer = User::lockForUpdate()->find($order->user_id);
                if(!$buyer) {
                    throw new \Exception('Buyer not found');
                }

                $buyer->balance += $order->price * $order->amount;
                $buyer->save();
            }

            if($order->side === OrderSide::SELL) {
                $asset = Asset::query()
                    ->where('user_id', $order->user_id)
                    ->where('symbol', $order->symbol)
                    ->lockForUpdate()
                    ->first();

                if(!$asset) {
                    throw new \Exception('Asset not found');
                }

                $asset->amount += $order->amount;
                $asset->locked_amount -= $order->amount;
                $asset->save();
            }

            return $order;
      });

    }
}
