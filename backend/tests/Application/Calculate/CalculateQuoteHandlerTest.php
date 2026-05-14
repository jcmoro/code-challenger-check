<?php

declare(strict_types=1);

namespace App\Tests\Application\Calculate;

use App\Application\Calculate\CalculateQuoteCommand;
use App\Application\Calculate\CalculateQuoteHandler;
use App\Application\Provider\FetchResult;
use App\Domain\Car\CarType;
use App\Domain\Car\CarUse;
use App\Domain\Money\Money;
use App\Domain\Quote\Quote;
use App\Tests\Support\FakeClock;
use App\Tests\Support\FixedCampaignProvider;
use App\Tests\Support\InMemoryQuoteFetcher;
use PHPUnit\Framework\TestCase;

final class CalculateQuoteHandlerTest extends TestCase
{
    public function testItReturnsQuotesSortedAscendingByPriceWithCampaignInactive(): void
    {
        $handler = $this->handler(
            campaign: FixedCampaignProvider::inactive(),
            quotes: [
                new Quote('provider-b', Money::eur(310.0)),
                new Quote('provider-a', Money::eur(295.0)),
            ],
            failed: [],
        );

        $result = $handler->handle($this->command());

        self::assertFalse($result->campaign->active);
        self::assertCount(2, $result->quotes);
        self::assertSame('provider-a', $result->quotes[0]->providerId);
        self::assertSame('provider-b', $result->quotes[1]->providerId);
        self::assertSame('provider-a', $result->cheapestProviderId());
        self::assertNull($result->quotes[0]->discountedPrice);
        self::assertNull($result->quotes[1]->discountedPrice);
    }

    public function testItAppliesTheCampaignDiscountAndSortsByDiscountedPrice(): void
    {
        $handler = $this->handler(
            campaign: FixedCampaignProvider::active(5.0),
            quotes: [
                new Quote('provider-a', Money::eur(295.0)),
                new Quote('provider-b', Money::eur(310.0)),
            ],
            failed: [],
        );

        $result = $handler->handle($this->command());

        self::assertTrue($result->campaign->active);
        self::assertSame(5.0, $result->campaign->percentage);
        self::assertCount(2, $result->quotes);

        $a = $result->quotes[0];
        self::assertSame('provider-a', $a->providerId);
        self::assertNotNull($a->discountedPrice);
        self::assertTrue(Money::eur(280.25)->equals($a->discountedPrice), 'A: 295 × 0.95 = 280.25');

        $b = $result->quotes[1];
        self::assertSame('provider-b', $b->providerId);
        self::assertNotNull($b->discountedPrice);
        self::assertTrue(Money::eur(294.5)->equals($b->discountedPrice), 'B: 310 × 0.95 = 294.50');
    }

    public function testItKeepsFailedProvidersOutOfTheQuotesButRecordsThemInMeta(): void
    {
        $handler = $this->handler(
            campaign: FixedCampaignProvider::inactive(),
            quotes: [new Quote('provider-a', Money::eur(295.0))],
            failed: ['provider-b', 'provider-c'],
        );

        $result = $handler->handle($this->command());

        self::assertCount(1, $result->quotes);
        self::assertSame(['provider-b', 'provider-c'], $result->failedProviderIds);
    }

    public function testItReturnsZeroQuotesWhenAllProvidersFail(): void
    {
        $handler = $this->handler(
            campaign: FixedCampaignProvider::active(5.0),
            quotes: [],
            failed: ['provider-a', 'provider-b', 'provider-c'],
        );

        $result = $handler->handle($this->command());

        self::assertSame([], $result->quotes);
        self::assertNull($result->cheapestProviderId());
        self::assertSame(['provider-a', 'provider-b', 'provider-c'], $result->failedProviderIds);
        self::assertTrue($result->campaign->active);
    }

    public function testItBreaksPriceTiesByProviderIdAlphabetical(): void
    {
        $handler = $this->handler(
            campaign: FixedCampaignProvider::inactive(),
            quotes: [
                new Quote('provider-c', Money::eur(250.0)),
                new Quote('provider-a', Money::eur(250.0)),
                new Quote('provider-b', Money::eur(250.0)),
            ],
            failed: [],
        );

        $result = $handler->handle($this->command());

        self::assertSame(
            ['provider-a', 'provider-b', 'provider-c'],
            array_map(static fn(Quote $q): string => $q->providerId, $result->quotes),
        );
    }

    public function testItComputesDriverAgeFromBirthdayUsingTheClock(): void
    {
        $fetcher = new InMemoryQuoteFetcher(new FetchResult([], []));
        $handler = new CalculateQuoteHandler(
            $fetcher,
            FixedCampaignProvider::inactive(),
            new FakeClock(new \DateTimeImmutable('2026-05-13')),
        );

        $handler->handle(new CalculateQuoteCommand(
            driverBirthday: new \DateTimeImmutable('1992-02-24'),
            carType: CarType::Turismo,
            carUse: CarUse::Private,
        ));

        self::assertNotNull($fetcher->lastAge);
        self::assertSame(34, $fetcher->lastAge->value);
    }

    /**
     * The previous discount test used 295×0.95 = 280.25, an exact decimal.
     * This locks the rounding contract for values whose multiplication
     * doesn't terminate cleanly.
     */
    public function testItRoundsTheDiscountedPriceToTwoDecimals(): void
    {
        $handler = $this->handler(
            campaign: FixedCampaignProvider::active(5.0),
            quotes: [
                new Quote('provider-a', Money::eur(299.99)), // ×0.95 = 284.9905 → 284.99
                new Quote('provider-b', Money::eur(333.33)), // ×0.95 = 316.6635 → 316.66
            ],
            failed: [],
        );

        $result = $handler->handle($this->command());

        self::assertNotNull($result->quotes[0]->discountedPrice);
        self::assertNotNull($result->quotes[1]->discountedPrice);
        self::assertTrue(
            Money::eur(284.99)->equals($result->quotes[0]->discountedPrice),
            'A: 299.99 × 0.95 = 284.9905 should round to 284.99',
        );
        self::assertTrue(
            Money::eur(316.66)->equals($result->quotes[1]->discountedPrice),
            'B: 333.33 × 0.95 = 316.6635 should round to 316.66',
        );
    }

    public function testASingleSurvivingQuoteIsMarkedAsCheapest(): void
    {
        $handler = $this->handler(
            campaign: FixedCampaignProvider::inactive(),
            quotes: [new Quote('provider-b', Money::eur(310.0))],
            failed: ['provider-a', 'provider-c'],
        );

        $result = $handler->handle($this->command());

        self::assertCount(1, $result->quotes);
        self::assertSame('provider-b', $result->cheapestProviderId());
    }

    public function testItRecordsAPositiveDurationMs(): void
    {
        $handler = $this->handler(
            campaign: FixedCampaignProvider::inactive(),
            quotes: [],
            failed: [],
        );

        $result = $handler->handle($this->command());

        self::assertGreaterThanOrEqual(0, $result->durationMs);
    }

    /**
     * @param list<Quote> $quotes
     * @param list<string> $failed
     */
    private function handler(FixedCampaignProvider $campaign, array $quotes, array $failed): CalculateQuoteHandler
    {
        return new CalculateQuoteHandler(
            new InMemoryQuoteFetcher(new FetchResult($quotes, $failed)),
            $campaign,
            new FakeClock(new \DateTimeImmutable('2026-05-13')),
        );
    }

    private function command(): CalculateQuoteCommand
    {
        return new CalculateQuoteCommand(
            driverBirthday: new \DateTimeImmutable('1992-02-24'),
            carType: CarType::Turismo,
            carUse: CarUse::Private,
        );
    }
}
