<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCancelled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order->load('user');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->order->user_id),
            new Channel('orderbook'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.cancelled';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order' => [
                'id' => $this->order->id,
                'user_id' => $this->order->user_id,
                'symbol' => $this->order->symbol,
                'side' => $this->order->side->value,
                'price' => (string) $this->order->price,
                'amount' => (string) $this->order->amount,
                'status' => $this->order->status->value,
            ],
        ];
    }
}
