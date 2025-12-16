<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
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
            'symbol' => $this->symbol,
            'amount' => (string) $this->amount,
            'locked_amount' => (string) $this->locked_amount,
            'available_amount' => (string) ($this->amount - $this->locked_amount),
        ];
    }
}
