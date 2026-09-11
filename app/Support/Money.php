<?php

namespace App\Support;

class Money
{
    public static function display(float|string|null $value): string
    {
        // Billing retains eight decimal places; only the display rounds upward.
        $units = round((float) $value * 100000000);

        return number_format(ceil($units / 1000000) / 100, 2, '.', ',');
    }
}
