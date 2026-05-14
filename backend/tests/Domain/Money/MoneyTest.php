<?php

declare(strict_types=1);

namespace App\Tests\Domain\Money;

use App\Domain\Money\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testItConstructsWithDefaultEurCurrency(): void
    {
        $money = new Money(295.0);

        self::assertSame(295.0, $money->amount);
        self::assertSame('EUR', $money->currency);
    }

    public function testEurFactoryShortcut(): void
    {
        self::assertTrue(Money::eur(50.5)->equals(new Money(50.5)));
    }

    public function testItRejectsNegativeAmounts(): void
    {
        self::expectException(\DomainException::class);

        new Money(-0.01);
    }

    public function testItRejectsEmptyCurrency(): void
    {
        self::expectException(\DomainException::class);

        new Money(10.0, '');
    }

    public function testAddSumsAmountsForMatchingCurrencies(): void
    {
        $sum = Money::eur(100.0)->add(Money::eur(25.5));

        self::assertTrue(Money::eur(125.5)->equals($sum));
    }

    public function testAddRejectsMismatchedCurrencies(): void
    {
        self::expectException(\DomainException::class);

        Money::eur(100.0)->add(new Money(100.0, 'USD'));
    }

    public function testMultiplyAppliesFactor(): void
    {
        // 295 × 0.95 = 280.25 (the campaign-discount case from validation.md §3.5).
        self::assertTrue(
            Money::eur(280.25)->equals(Money::eur(295.0)->multiply(0.95)),
        );
    }

    public function testRoundedClampsToDecimalPlaces(): void
    {
        self::assertTrue(
            Money::eur(280.25)->equals(Money::eur(280.2456)->rounded()),
        );
    }

    public function testEqualsAllowsTinyFloatingPointDrift(): void
    {
        self::assertTrue(Money::eur(100.0001)->equals(Money::eur(100.0)));
        self::assertFalse(Money::eur(100.01)->equals(Money::eur(100.0)));
    }
}
