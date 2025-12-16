<?php

namespace App\DTOs;

use App\Enums\OrderSide;
use App\Enums\Symbol;
use App\Models\User;

class CreateLimitOrderDTO
{
    public function __construct(
        public readonly User $user,
        public readonly Symbol $symbol,
        public readonly OrderSide $side,
        public readonly float $price,
        public readonly float $amount,
    ) {
    }

    public static function fromRequest(User $user, array $validated): self
    {
        return new self(
            user: $user,
            symbol: Symbol::from($validated['symbol']),
            side: OrderSide::from($validated['side']),
            price: (float) $validated['price'],
            amount: (float) $validated['amount'],
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->user->id,
            'symbol' => $this->symbol->value,
            'side' => $this->side->value,
            'price' => $this->price,
            'amount' => $this->amount,
        ];
    }
}

