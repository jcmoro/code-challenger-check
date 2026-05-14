<?php

declare(strict_types=1);

namespace App\Tests\Domain\Quote;

use App\Domain\Money\Money;
use App\Domain\Quote\Quote;
use PHPUnit\Framework\TestCase;

final class QuoteTest extends TestCase
{
    public function testItConstructsWithoutDiscount(): void
    {
        $quote = new Quote('provider-a', Money::eur(295.0));

        self::assertSame('provider-a', $quote->providerId);
        self::assertTrue(Money::eur(295.0)->equals($quote->price));
        self::assertNull($quote->discountedPrice);
        self::assertTrue($quote->price->equals($quote->finalPrice()));
    }

    public function testItExposesDiscountedPriceAsFinalWhenPresent(): void
    {
        $quote = (new Quote('provider-a', Money::eur(295.0)))
            ->withDiscountedPrice(Money::eur(280.25));

        self::assertTrue(Money::eur(280.25)->equals($quote->finalPrice()));
    }

    public function testItRejectsEmptyProviderId(): void
    {
        self::expectException(\DomainException::class);

        new Quote('', Money::eur(100.0));
    }

    public function testWithDiscountedPriceReturnsNewInstance(): void
    {
        $original = new Quote('provider-a', Money::eur(295.0));
        $discounted = $original->withDiscountedPrice(Money::eur(280.25));

        self::assertNull($original->discountedPrice);
        self::assertNotNull($discounted->discountedPrice);
    }
}
