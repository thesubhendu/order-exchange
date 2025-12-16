<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'symbol' => $this->symbol,
            'side' => $this->side->value,
            'price' => (string) $this->price,
            'amount' => (string) $this->amount,
            'status' => $this->status->value,
            'status_label' => match($this->status) {
                \App\Enums\OrderStatus::OPEN => 'open',
                \App\Enums\OrderStatus::FILLED => 'filled',
                \App\Enums\OrderStatus::CANCELLED => 'cancelled',
            },
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
        ];
    }
}
