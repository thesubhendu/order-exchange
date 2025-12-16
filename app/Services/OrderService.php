<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Order;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\DTOs\CreateLimitOrderDTO;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
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

            $matchingSellOrder = Order::open()
                ->where('symbol', $dto->symbol)
                ->where('side', OrderSide::SELL)
                ->where('price', '<=', $dto->price)
                ->oldest()
                ->lockForUpdate()
                ->first();


            if ($matchingSellOrder) {
                $lockedBuyOrder = Order::lockForUpdate()->find($buyOrder->id);

                if ($lockedBuyOrder && $lockedBuyOrder->status === OrderStatus::OPEN) {
                    try {
                        $this->fillOrder($lockedBuyOrder, $matchingSellOrder);
                        $buyOrder->refresh();
                    } catch (\Exception $e) {
                        logger()->error('Failed to fill order', ['error' => $e->getMessage()]);
                    }
                }
            }

            return $buyOrder;
        });
    }

    private function fillOrder(Order $buyOrder, Order $sellOrder): void
    {
        DB::transaction(function () use ($buyOrder, $sellOrder) {
            $lockedBuyOrder = Order::lockForUpdate()->find($buyOrder->id);
            $lockedSellOrder = Order::lockForUpdate()->find($sellOrder->id);

            if (!$lockedBuyOrder || !$lockedSellOrder) {
                throw new \Exception('Order not found');
            }

            if ($lockedBuyOrder->status !== OrderStatus::OPEN ||
                $lockedSellOrder->status !== OrderStatus::OPEN) {
                throw new \Exception('Order cannot be filled - status changed');
            }

            $lockedBuyOrder->status = OrderStatus::FILLED;
            $lockedBuyOrder->save();
            $lockedSellOrder->status = OrderStatus::FILLED;
            $lockedSellOrder->save();

            $totalSellingPrice = $lockedSellOrder->price * $lockedSellOrder->amount;
            $commission = 0.015 * $totalSellingPrice;

            $seller = User::lockForUpdate()->find($lockedSellOrder->user_id);
            if (!$seller) {
                throw new \Exception('Seller not found');
            }
            $seller->balance -= $commission;
            $seller->save();

            $asset = Asset::query()
                ->where(['symbol' => $lockedBuyOrder->symbol, 'user_id' => $lockedBuyOrder->user_id])
                ->lockForUpdate()
                ->first();

            if (!$asset) {
                Asset::query()->create([
                    'symbol' => $lockedBuyOrder->symbol,
                    'user_id' => $lockedBuyOrder->user_id,
                    'amount' => $lockedSellOrder->amount,
                ]);
            } else {
                $asset->amount += $lockedSellOrder->amount;
                $asset->save();
            }

            // Release locked amount from seller's asset
            $sellerAsset = Asset::query()
                ->where(['symbol' => $lockedSellOrder->symbol, 'user_id' => $lockedSellOrder->user_id])
                ->lockForUpdate()
                ->first();

            if ($sellerAsset) {
                $sellerAsset->locked_amount -= $lockedSellOrder->amount;
                $sellerAsset->save();
            }
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

            if ($asset->amount < $dto->amount) {
                throw new \Exception('Insufficient assets');
            }

            $asset->amount -= $dto->amount;
            $asset->locked_amount += $dto->amount;
            $asset->save();

            $sellOrder = Order::create($dto->toArray() + ['status' => OrderStatus::OPEN]);

            $matchingBuyOrder = Order::open()
                ->where('symbol', $dto->symbol)
                ->where('side', OrderSide::BUY)
                ->where('price', '>=', $dto->price)
                ->oldest()
                ->lockForUpdate()
                ->first();


            if ($matchingBuyOrder) {
                $lockedSellOrder = Order::lockForUpdate()->find($sellOrder->id);
                if ($lockedSellOrder && $lockedSellOrder->status === OrderStatus::OPEN) {
                    try {
                        $this->fillOrder($matchingBuyOrder, $lockedSellOrder);
                        $sellOrder->refresh();
                    } catch (\Exception $e) {
                       logger()->error('Failed to fill order', ['error' => $e->getMessage()]);
                    }
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
