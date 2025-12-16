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

            // Broadcast order created event
            event(new OrderCreated($buyOrder));

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
        // Note: This is called within a transaction, so no nested transaction needed
        $lockedBuyOrder = Order::lockForUpdate()->find($buyOrder->id);
        $lockedSellOrder = Order::lockForUpdate()->find($sellOrder->id);

        if (!$lockedBuyOrder || !$lockedSellOrder) {
            throw new \Exception('Order not found');
        }

        if ($lockedBuyOrder->status !== OrderStatus::OPEN ||
            $lockedSellOrder->status !== OrderStatus::OPEN) {
            throw new \Exception('Order cannot be filled - status changed');
        }

        // Trade executes at sell order price (price-time priority)
        $executionPrice = $lockedSellOrder->price;
        $executionAmount = $lockedSellOrder->amount;
        $totalValue = $executionPrice * $executionAmount;
        $commission = 0.015 * $totalValue; // 1.5% commission

        // Lock both users for balance updates
        $buyer = User::lockForUpdate()->find($lockedBuyOrder->user_id);
        $seller = User::lockForUpdate()->find($lockedSellOrder->user_id);

        if (!$buyer || !$seller) {
            throw new \Exception('User not found');
        }

        // Buyer: Already paid buyOrder.price * amount, but trade executes at sellOrder.price
        // Commission is deducted from buyer (as per requirements)
        // Refund the difference: (buyOrder.price - sellOrder.price) * amount, minus commission
        $buyerPaid = $lockedBuyOrder->price * $lockedBuyOrder->amount;
        $buyerShouldPay = $totalValue + $commission; // Pay execution price + commission
        $buyerRefund = $buyerPaid - $buyerShouldPay;
        
        if ($buyerRefund > 0) {
            $buyer->balance += $buyerRefund;
        } elseif ($buyerRefund < 0) {
            // This shouldn't happen if validation is correct, but handle it
            throw new \Exception('Insufficient balance for trade execution');
        }
        $buyer->save();

        // Seller: Receive full sale proceeds (commission already deducted from buyer)
        $seller->balance += $totalValue;
        $seller->save();

        // Update order statuses
        $lockedBuyOrder->status = OrderStatus::FILLED;
        $lockedBuyOrder->save();
        $lockedSellOrder->status = OrderStatus::FILLED;
        $lockedSellOrder->save();

        // Give assets to buyer
        $buyerAsset = Asset::query()
            ->where(['symbol' => $lockedBuyOrder->symbol, 'user_id' => $lockedBuyOrder->user_id])
            ->lockForUpdate()
            ->first();

        if (!$buyerAsset) {
            Asset::query()->create([
                'symbol' => $lockedBuyOrder->symbol,
                'user_id' => $lockedBuyOrder->user_id,
                'amount' => $executionAmount,
                'locked_amount' => 0,
            ]);
        } else {
            $buyerAsset->amount += $executionAmount;
            $buyerAsset->save();
        }

        // Release locked amount from seller's asset
        $sellerAsset = Asset::query()
            ->where(['symbol' => $lockedSellOrder->symbol, 'user_id' => $lockedSellOrder->user_id])
            ->lockForUpdate()
            ->first();

        if ($sellerAsset) {
            $sellerAsset->locked_amount -= $executionAmount;
            $sellerAsset->save();
        }

        // Broadcast OrderMatched event
        event(new \App\Events\OrderMatched($lockedBuyOrder, $lockedSellOrder, $executionPrice, $executionAmount, $commission));
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

            // Check available amount (amount - locked_amount)
            $availableAmount = $asset->amount - $asset->locked_amount;
            if ($availableAmount < $dto->amount) {
                throw new \Exception('Insufficient assets');
            }

            $asset->amount -= $dto->amount;
            $asset->locked_amount += $dto->amount;
            $asset->save();

            $sellOrder = Order::create($dto->toArray() + ['status' => OrderStatus::OPEN]);

            // Broadcast order created event
            event(new OrderCreated($sellOrder));

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

            // Broadcast order cancelled event
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
