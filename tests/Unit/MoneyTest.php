<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_amounts_round_up_without_adding_a_cent_to_exact_cent_values(): void
    {
        foreach (['0' => '0.00', '0.00000001' => '0.01', '1.001' => '1.01', '1.01' => '1.01', '1.10' => '1.10', '1.2301' => '1.24', '999.999' => '1,000.00'] as $input => $expected) {
            $this->assertSame($expected, Money::display($input));
        }
    }
}
