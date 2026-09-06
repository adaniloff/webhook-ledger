<?php

namespace App\Enum;

enum SourceEnum: string
{
    case STRIPE = 'stripe';
    case GITHUB = 'github';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, static::cases());
    }
}
