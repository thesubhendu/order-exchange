<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMatched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $buyOrder;
    public Order $sellOrder;
    public float $executionPrice;
    public float $executionAmount;
    public float $commission;

    /**
     * Create a new event instance.
     */
    public function __construct(
        Order $buyOrder,
        Order $sellOrder,
        float $executionPrice,
        float $executionAmount,
        float $commission
    ) {
        $this->buyOrder = $buyOrder->load('user');
        $this->sellOrder = $sellOrder->load('user');
        $this->executionPrice = $executionPrice;
        $this->executionAmount = $executionAmount;
        $this->commission = $commission;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->buyOrder->user_id),
            new PrivateChannel('user.' . $this->sellOrder->user_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.matched';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'buy_order' => [
                'id' => $this->buyOrder->id,
                'user_id' => $this->buyOrder->user_id,
                'symbol' => $this->buyOrder->symbol,
                'side' => $this->buyOrder->side->value,
                'price' => (string) $this->buyOrder->price,
                'amount' => (string) $this->buyOrder->amount,
                'status' => $this->buyOrder->status->value,
            ],
            'sell_order' => [
                'id' => $this->sellOrder->id,
                'user_id' => $this->sellOrder->user_id,
                'symbol' => $this->sellOrder->symbol,
                'side' => $this->sellOrder->side->value,
                'price' => (string) $this->sellOrder->price,
                'amount' => (string) $this->sellOrder->amount,
                'status' => $this->sellOrder->status->value,
            ],
            'execution_price' => (string) $this->executionPrice,
            'execution_amount' => (string) $this->executionAmount,
            'commission' => (string) $this->commission,
            'total_value' => (string) ($this->executionPrice * $this->executionAmount),
        ];
    }
}
