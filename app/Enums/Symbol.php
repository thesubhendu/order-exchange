<?php

namespace App\Enums;

enum Symbol: string
{
    case BTC = 'BTC';
    case ETH = 'ETH';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

