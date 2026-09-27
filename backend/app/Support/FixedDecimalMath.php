<?php

namespace App\Support;

use InvalidArgumentException;
use OverflowException;

final class FixedDecimalMath
{
    public static function normalizeSigned(string|int $value, int $maxIntegerDigits, int $scale, string $field): string
    {
        $value = (string) $value;

        if (! preg_match('/\A-?\d{1,'.$maxIntegerDigits.'}(?:\.\d{1,'.$scale.'})?\z/', $value)) {
            throw new InvalidArgumentException("{$field} must be a signed decimal with at most {$scale} fractional digits.");
        }

        $negative = str_starts_with($value, '-');
        $normalized = self::normalize($negative ? substr($value, 1) : $value, $maxIntegerDigits, $scale, $field);

        return $negative && ! self::isZero($normalized) ? '-'.$normalized : $normalized;
    }

    public static function subtractSigned(string|int $left, string|int $right, int $maxIntegerDigits, int $scale): string
    {
        $left = self::normalizeSigned($left, $maxIntegerDigits, $scale, 'left operand');
        $right = self::normalizeSigned($right, $maxIntegerDigits, $scale, 'right operand');
        $leftNegative = str_starts_with($left, '-');
        $rightNegative = str_starts_with($right, '-');
        $leftAbsolute = $leftNegative ? substr($left, 1) : $left;
        $rightAbsolute = $rightNegative ? substr($right, 1) : $right;

        if ($leftNegative !== $rightNegative) {
            $sum = self::add(
                $leftAbsolute,
                $rightAbsolute,
                $maxIntegerDigits,
                $scale,
            );

            return $leftNegative ? '-'.$sum : $sum;
        }

        $comparison = self::compareIntegers(
            self::scaledInteger($leftAbsolute),
            self::scaledInteger($rightAbsolute),
        );

        if ($comparison === 0) {
            return self::formatScaledInteger('0', $maxIntegerDigits, $scale);
        }

        $larger = $comparison > 0 ? $leftAbsolute : $rightAbsolute;
        $smaller = $comparison > 0 ? $rightAbsolute : $leftAbsolute;
        $difference = self::subtractNonNegative($larger, $smaller, $maxIntegerDigits, $scale);
        $resultNegative = $comparison > 0 ? $leftNegative : ! $leftNegative;

        return $resultNegative ? '-'.$difference : $difference;
    }

    public static function normalize(string $value, int $maxIntegerDigits, int $scale, string $field): string
    {
        if ($maxIntegerDigits < 1 || $scale < 1) {
            throw new InvalidArgumentException('Decimal precision must be positive.');
        }

        $pattern = '/\A\d{1,'.$maxIntegerDigits.'}(?:\.\d{1,'.$scale.'})?\z/';

        if (! preg_match($pattern, $value)) {
            throw new InvalidArgumentException("{$field} must be a non-negative decimal with at most {$scale} fractional digits.");
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = self::normalizeInteger($whole);

        return $whole.'.'.str_pad($fraction, $scale, '0');
    }

    public static function isZero(string $value): bool
    {
        return self::scaledInteger($value) === '0';
    }

    public static function add(string $left, string $right, int $maxIntegerDigits, int $scale): string
    {
        $left = self::normalize($left, $maxIntegerDigits, $scale, 'left operand');
        $right = self::normalize($right, $maxIntegerDigits, $scale, 'right operand');

        return self::formatScaledInteger(
            self::addIntegers(self::scaledInteger($left), self::scaledInteger($right)),
            $maxIntegerDigits,
            $scale,
        );
    }

    public static function subtractNonNegative(string $left, string $right, int $maxIntegerDigits, int $scale): string
    {
        $left = self::normalize($left, $maxIntegerDigits, $scale, 'left operand');
        $right = self::normalize($right, $maxIntegerDigits, $scale, 'right operand');
        $leftInteger = self::scaledInteger($left);
        $rightInteger = self::scaledInteger($right);

        if (self::compareIntegers($leftInteger, $rightInteger) < 0) {
            throw new InvalidArgumentException('Decimal subtraction cannot produce a negative balance.');
        }

        return self::formatScaledInteger(
            self::subtractIntegers($leftInteger, $rightInteger),
            $maxIntegerDigits,
            $scale,
        );
    }

    public static function multiplyToScale(
        string $left,
        int $leftScale,
        string $right,
        int $rightScale,
        int $outputScale,
        int $outputMaxIntegerDigits,
    ): string {
        $product = self::multiplyIntegers(self::scaledInteger($left), self::scaledInteger($right));
        $discardedDigits = $leftScale + $rightScale - $outputScale;

        if ($discardedDigits <= 0) {
            $outputInteger = $product.str_repeat('0', -$discardedDigits);
        } else {
            $outputInteger = strlen($product) > $discardedDigits
                ? substr($product, 0, -$discardedDigits)
                : '0';
            $remainder = str_pad(substr($product, -$discardedDigits), $discardedDigits, '0', STR_PAD_LEFT);
            $roundingThreshold = '5'.str_repeat('0', $discardedDigits - 1);

            if (self::compareIntegers($remainder, $roundingThreshold) >= 0) {
                $outputInteger = self::addIntegers($outputInteger, '1');
            }
        }

        return self::formatScaledInteger($outputInteger, $outputMaxIntegerDigits, $outputScale);
    }

    public static function divideToScale(
        string $numerator,
        int $numeratorScale,
        string $denominator,
        int $denominatorScale,
        int $outputScale,
        int $outputMaxIntegerDigits,
    ): string {
        if ($numeratorScale < 0 || $denominatorScale < 0 || $outputScale < 1) {
            throw new InvalidArgumentException('Decimal scales are invalid.');
        }

        $numeratorInteger = self::scaledInteger($numerator);
        $denominatorInteger = self::scaledInteger($denominator);

        if ($denominatorInteger === '0') {
            throw new InvalidArgumentException('Decimal division by zero is not allowed.');
        }

        $power = $denominatorScale + $outputScale - $numeratorScale;

        if ($power >= 0) {
            $numeratorInteger .= str_repeat('0', $power);
        } else {
            $denominatorInteger .= str_repeat('0', -$power);
        }

        [$quotient, $remainder] = self::divideIntegers($numeratorInteger, $denominatorInteger);

        if (self::compareIntegers(self::multiplyIntegers($remainder, '2'), $denominatorInteger) >= 0) {
            $quotient = self::addIntegers($quotient, '1');
        }

        return self::formatScaledInteger($quotient, $outputMaxIntegerDigits, $outputScale);
    }

    private static function scaledInteger(string $value): string
    {
        return self::normalizeInteger(str_replace('.', '', $value));
    }

    private static function formatScaledInteger(string $value, int $maxIntegerDigits, int $scale): string
    {
        $digits = self::normalizeInteger($value);
        $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
        $whole = self::normalizeInteger(substr($digits, 0, -$scale));

        if (strlen($whole) > $maxIntegerDigits) {
            throw new OverflowException('Decimal result exceeds the supported precision.');
        }

        return $whole.'.'.substr($digits, -$scale);
    }

    private static function normalizeInteger(string $value): string
    {
        return ltrim($value, '0') ?: '0';
    }

    private static function compareIntegers(string $left, string $right): int
    {
        $left = self::normalizeInteger($left);
        $right = self::normalizeInteger($right);

        if (strlen($left) !== strlen($right)) {
            return strlen($left) <=> strlen($right);
        }

        return strcmp($left, $right);
    }

    private static function addIntegers(string $left, string $right): string
    {
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;
        $carry = 0;
        $result = '';

        while ($leftIndex >= 0 || $rightIndex >= 0 || $carry > 0) {
            $sum = $carry;
            $sum += $leftIndex >= 0 ? (int) $left[$leftIndex--] : 0;
            $sum += $rightIndex >= 0 ? (int) $right[$rightIndex--] : 0;
            $result .= (string) ($sum % 10);
            $carry = intdiv($sum, 10);
        }

        return strrev($result);
    }

    private static function subtractIntegers(string $left, string $right): string
    {
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;
        $borrow = 0;
        $result = '';

        while ($leftIndex >= 0) {
            $digit = (int) $left[$leftIndex--] - $borrow;
            $digit -= $rightIndex >= 0 ? (int) $right[$rightIndex--] : 0;

            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $result .= (string) $digit;
        }

        return self::normalizeInteger(strrev($result));
    }

    private static function multiplyIntegers(string $left, string $right): string
    {
        $left = self::normalizeInteger($left);
        $right = self::normalizeInteger($right);

        if ($left === '0' || $right === '0') {
            return '0';
        }

        $leftDigits = array_reverse(array_map('intval', str_split($left)));
        $rightDigits = array_reverse(array_map('intval', str_split($right)));
        $result = array_fill(0, count($leftDigits) + count($rightDigits), 0);

        foreach ($leftDigits as $leftIndex => $leftDigit) {
            foreach ($rightDigits as $rightIndex => $rightDigit) {
                $result[$leftIndex + $rightIndex] += $leftDigit * $rightDigit;
            }
        }

        for ($index = 0; $index < count($result) - 1; $index++) {
            $carry = intdiv($result[$index], 10);
            $result[$index] %= 10;
            $result[$index + 1] += $carry;
        }

        while (end($result) >= 10) {
            $carry = intdiv($result[array_key_last($result)], 10);
            $result[array_key_last($result)] %= 10;
            $result[] = $carry;
        }

        return self::normalizeInteger(implode('', array_reverse($result)));
    }

    private static function divideIntegers(string $dividend, string $divisor): array
    {
        $quotient = '';
        $remainder = '0';

        foreach (str_split(self::normalizeInteger($dividend)) as $digit) {
            $remainder = self::normalizeInteger(($remainder === '0' ? '' : $remainder).$digit);
            $quotientDigit = 0;

            for ($candidate = 9; $candidate >= 1; $candidate--) {
                if (self::compareIntegers(self::multiplyIntegers($divisor, (string) $candidate), $remainder) <= 0) {
                    $quotientDigit = $candidate;
                    break;
                }
            }

            if ($quotientDigit > 0) {
                $remainder = self::subtractIntegers(
                    $remainder,
                    self::multiplyIntegers($divisor, (string) $quotientDigit),
                );
            }

            $quotient .= (string) $quotientDigit;
        }

        return [self::normalizeInteger($quotient), self::normalizeInteger($remainder)];
    }
}
