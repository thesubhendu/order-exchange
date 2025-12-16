<?php

namespace App\Services;

use App\Models\Order;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\DTOs\CreateLimitOrderDTO;
use Illuminate\Support\Collection;

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
        $userBalance = $dto->user->balance;
        $assetPrice = $dto->price*$dto->amount;
        if($userBalance < $assetPrice) {
            throw new \Exception('Insufficient balance');
        }
        $dto->user->balance -= $assetPrice;
        $dto->user->save();

        $buyOrder = Order::create($dto->toArray()+['status' => OrderStatus::OPEN]);

        // check if matching sell order is available
        $matchingSellOrder = Order::open()->where('symbol', $dto->symbol)->where('side', OrderSide::SELL)->where('price', '<=', $dto->price)->oldest()->first();
       
        if($matchingSellOrder) {
            $this->fillOrder($buyOrder, $matchingSellOrder);
        }

        return $buyOrder;
    }

    private function fillOrder(Order $buyOrder, Order $sellOrder): void
    {
        $buyOrder->status = OrderStatus::FILLED;
        $buyOrder->save();
        $sellOrder->status = OrderStatus::FILLED;
        $sellOrder->save();
    }

    private function sellOrder(CreateLimitOrderDTO $dto): Order
    {
        $asset = Asset::where('user_id', $dto->user->id)->where('symbol', $dto->symbol)->first();
        // check if enough assets are available
        if($asset->amount < $dto->amount) {
            throw new \Exception('Insufficient assets');
        }
        
        $asset->amount -= $dto->amount;
        $asset->locked_amount += $dto->amount;
        $asset->save();

        $sellOrder = Order::create($dto->toArray()+['status' => OrderStatus::OPEN]);

        // check if matching buy order is available
        $matchingBuyOrder = Order::open()->where('symbol', $dto->symbol)->where('side', OrderSide::BUY)->where('price', '>=', $dto->price)->oldest()->first();
        if($matchingBuyOrder) {
            $this->fillOrder($matchingBuyOrder, $sellOrder);
        }

        return $sellOrder;

    }

    public function cancelOrder(string $id): Order
    {
        $order = Order::find($id);
        if(!$order) {
            throw new \Exception('Order not found');
        }
        $order->status = OrderStatus::CANCELLED;
        $order->save();

        if($order->side === OrderSide::BUY) {
            $order->user->balance += $order->price * $order->amount;
            $order->user->save();
        }

        if($order->side === OrderSide::SELL) {
            $asset = Asset::where('user_id', $order->user_id)->where('symbol', $order->symbol)->first();
            $asset->amount += $order->amount;
            $asset->locked_amount -= $order->amount;
            $asset->save();
        }

        return $order;
    }
}
