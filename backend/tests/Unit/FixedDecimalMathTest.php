<?php

namespace Tests\Unit;

use App\Support\FixedDecimalMath;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FixedDecimalMathTest extends TestCase
{
    public function test_division_uses_fixed_scale_and_half_up_rounding(): void
    {
        $this->assertSame(
            '0.002000000000000000',
            FixedDecimalMath::divideToScale('100.00', 2, '50000.00000000', 8, 18, 18),
        );
        $this->assertSame(
            '0.333333333333333333',
            FixedDecimalMath::divideToScale('1.00', 2, '3.00000000', 8, 18, 18),
        );
    }

    public function test_division_rejects_zero_denominator(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FixedDecimalMath::divideToScale('1.00', 2, '0.00000000', 8, 18, 18);
    }
}
