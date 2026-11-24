<?php

namespace App\Support;

use Illuminate\Support\Number;

class Money
{
    public static function currency(): string
    {
        return config('shop.currency', 'USD');
    }

    public static function format(float|int|string|null $amount): ?string
    {
        return $amount === null ? null : Number::currency((float) $amount, in: static::currency());
    }
}
