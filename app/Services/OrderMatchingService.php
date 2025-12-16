<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Enums\OrderStatus;
use App\Events\OrderMatched;

class OrderMatchingService
{
    private Order $buyOrder;
    private Order $sellOrder;
    private array $execution;

    public function __construct(
        Order $buyOrder,
        Order $sellOrder,
        private readonly AssetTransferService $assetTransferService
    ) {
        $this->buyOrder = $buyOrder;
        $this->sellOrder = $sellOrder;
    }

    /**
     * Match and execute a trade between the buy order and sell order.
     * This method assumes it's being called within a database transaction.
     * 
     */
    public function execute(): void
    {
        $this->lockAndValidateOrders();
        $this->calculateExecution();
        $this->processBalances();
        $this->transferAssets();
        $this->completeOrders();
        $this->dispatchEvent();
    }

    
    private function lockAndValidateOrders(): void
    {
        // Lock orders in ascending ID order to prevent deadlocks
        $orderIds = [$this->buyOrder->id, $this->sellOrder->id];
        sort($orderIds);

        $lockedOrders = Order::query()
            ->whereIn('id', $orderIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $this->buyOrder = $lockedOrders->get($this->buyOrder->id);
        $this->sellOrder = $lockedOrders->get($this->sellOrder->id);

        if (!$this->buyOrder || !$this->sellOrder) {
            throw new \Exception('Order not found');
        }

        if ($this->buyOrder->status !== OrderStatus::OPEN || $this->sellOrder->status !== OrderStatus::OPEN) {
            throw new \Exception('Order cannot be filled - status changed');
        }

        if ($this->buyOrder->price < $this->sellOrder->price) {
            throw new \Exception('Orders cannot match - buy price is less than sell price');
        }
    }


    private function calculateExecution(): void
    {
        $executionPrice = $this->sellOrder->price;
        $executionAmount = min($this->buyOrder->amount, $this->sellOrder->amount);
        $totalValue = $executionPrice * $executionAmount;
        $commission = 0.015 * $totalValue; // 1.5% commission

        $this->execution = [
            'price' => $executionPrice,
            'amount' => $executionAmount,
            'total_value' => $totalValue,
            'commission' => $commission,
        ];
    }

    
    private function processBalances(): void
    {
        $userIds = [$this->buyOrder->user_id, $this->sellOrder->user_id];
        sort($userIds);

        $lockedUsers = User::query()
            ->whereIn('id', $userIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $buyer = $lockedUsers->get($this->buyOrder->user_id);
        $seller = $lockedUsers->get($this->sellOrder->user_id);

        if (!$buyer || !$seller) {
            throw new \Exception('User not found');
        }

        $this->adjustBuyerBalance($buyer);
        $this->adjustSellerBalance($seller);
    }

    
    private function adjustBuyerBalance(User $buyer): void
    {
        $buyerPaid = $this->buyOrder->price * $this->buyOrder->amount;
        $buyerShouldPayForExecution = $this->execution['total_value'] + $this->execution['commission'];
        $buyerRefund = $buyerPaid - $buyerShouldPayForExecution;

        if ($buyerRefund > 0) {
            $buyer->balance += $buyerRefund;
        } elseif ($buyerRefund < 0) {
            $additionalPaymentNeeded = abs($buyerRefund);
            if ($buyer->balance < $additionalPaymentNeeded) {
                throw new \Exception('Insufficient balance for trade execution and commission');
            }
            $buyer->balance -= $additionalPaymentNeeded;
        }

        $buyer->save();
    }

    
    private function adjustSellerBalance(User $seller): void
    {
        $seller->balance += $this->execution['total_value'];
        $seller->save();
    }

    private function transferAssets(): void
    {
        $this->assetTransferService->creditAsset(
            $this->buyOrder->user_id,
            $this->buyOrder->symbol,
            $this->execution['amount']
        );

        $this->assetTransferService->debitLockedAsset(
            $this->sellOrder->user_id,
            $this->sellOrder->symbol,
            $this->execution['amount']
        );
    }

   
    private function completeOrders(): void
    {
        $this->buyOrder->status = OrderStatus::FILLED;
        $this->buyOrder->save();

        $this->sellOrder->status = OrderStatus::FILLED;
        $this->sellOrder->save();
    }

   
    private function dispatchEvent(): void
    {
        event(new OrderMatched(
            $this->buyOrder,
            $this->sellOrder,
            $this->execution['price'],
            $this->execution['amount'],
            $this->execution['commission']
        ));
    }
}
