<?php

namespace App\Enums;

enum OrderSide: string
{
    case BUY = 'buy';
    case SELL = 'sell';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

