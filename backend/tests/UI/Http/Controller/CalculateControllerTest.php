<?php

declare(strict_types=1);

namespace App\Tests\UI\Http\Controller;

use App\Application\Campaign\CampaignProvider;
use App\Application\Provider\FetchResult;
use App\Application\Provider\QuoteFetcher;
use App\Domain\Money\Money;
use App\Domain\Quote\Quote;
use App\Infrastructure\System\Clock;
use App\Tests\Support\FakeClock;
use App\Tests\Support\FixedCampaignProvider;
use App\Tests\Support\InMemoryQuoteFetcher;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-type MoneyShape           array{amount: float, currency: string}
 * @phpstan-type QuoteShape           array{
 *     provider: string,
 *     price: MoneyShape,
 *     discounted_price: MoneyShape|null,
 *     is_cheapest: bool
 * }
 * @phpstan-type CampaignShape        array{active: bool, percentage: float}
 * @phpstan-type MetaShape            array{duration_ms: int, failed_providers: list<string>}
 * @phpstan-type CalculateResponseShape array{
 *     campaign: CampaignShape,
 *     quotes: list<QuoteShape>,
 *     meta: MetaShape
 * }
 * @phpstan-type ViolationShape       array{field: string, message: string}
 * @phpstan-type ProblemDetailsShape  array{error: string, violations: list<ViolationShape>}
 */
final class CalculateControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        static::getContainer()->set(Clock::class, new FakeClock(new \DateTimeImmutable('2026-05-13')));
    }

    public function testItReturnsThreeQuotesSortedWithCampaignDiscountApplied(): void
    {
        $this->withCampaign(FixedCampaignProvider::active(5.0));
        $this->withFetcher(new InMemoryQuoteFetcher(new FetchResult(
            quotes: [
                new Quote('provider-a', Money::eur(317.0)),
                new Quote('provider-b', Money::eur(300.0)),
                new Quote('provider-c', Money::eur(210.0)),
            ],
            failedProviderIds: [],
        )));

        $this->client->jsonRequest('POST', '/calculate', [
            'driver_birthday' => '1992-02-24',
            'car_type' => 'Turismo',
            'car_use' => 'Privado',
        ]);

        self::assertResponseIsSuccessful();
        $body = $this->calculateResponse();

        self::assertTrue($body['campaign']['active']);
        self::assertSame(5.0, $body['campaign']['percentage']);

        self::assertSame(
            ['provider-c', 'provider-b', 'provider-a'],
            array_map(static fn (array $q): string => $q['provider'], $body['quotes']),
        );

        self::assertTrue($body['quotes'][0]['is_cheapest']);
        self::assertFalse($body['quotes'][1]['is_cheapest']);
        self::assertFalse($body['quotes'][2]['is_cheapest']);

        self::assertSame(210.0, $body['quotes'][0]['price']['amount']);
        self::assertNotNull($body['quotes'][0]['discounted_price']);
        self::assertSame(199.5, $body['quotes'][0]['discounted_price']['amount']);
        self::assertSame('EUR', $body['quotes'][0]['price']['currency']);

        self::assertSame([], $body['meta']['failed_providers']);
    }

    public function testItOmitsDiscountedPriceWhenCampaignIsInactive(): void
    {
        $this->withCampaign(FixedCampaignProvider::inactive());
        $this->withFetcher(new InMemoryQuoteFetcher(new FetchResult(
            quotes: [
                new Quote('provider-a', Money::eur(317.0)),
                new Quote('provider-b', Money::eur(300.0)),
                new Quote('provider-c', Money::eur(210.0)),
            ],
            failedProviderIds: [],
        )));

        $this->client->jsonRequest('POST', '/calculate', [
            'driver_birthday' => '1992-02-24',
            'car_type' => 'Turismo',
            'car_use' => 'Privado',
        ]);

        self::assertResponseIsSuccessful();
        $body = $this->calculateResponse();

        self::assertFalse($body['campaign']['active']);
        foreach ($body['quotes'] as $quote) {
            self::assertNull($quote['discounted_price']);
        }
    }

    public function testItDropsFailedProvidersAndStillReturns200(): void
    {
        $this->withCampaign(FixedCampaignProvider::inactive());
        $this->withFetcher(new InMemoryQuoteFetcher(new FetchResult(
            quotes: [new Quote('provider-b', Money::eur(300.0))],
            failedProviderIds: ['provider-a', 'provider-c'],
        )));

        $this->client->jsonRequest('POST', '/calculate', [
            'driver_birthday' => '1992-02-24',
            'car_type' => 'Turismo',
            'car_use' => 'Privado',
        ]);

        self::assertResponseIsSuccessful();
        $body = $this->calculateResponse();

        self::assertCount(1, $body['quotes']);
        self::assertSame('provider-b', $body['quotes'][0]['provider']);
        $failed = $body['meta']['failed_providers'];
        sort($failed);
        self::assertSame(['provider-a', 'provider-c'], $failed);
    }

    public function testItReturns200WithEmptyQuotesWhenAllProvidersFail(): void
    {
        $this->withCampaign(FixedCampaignProvider::active(5.0));
        $this->withFetcher(new InMemoryQuoteFetcher(new FetchResult(
            quotes: [],
            failedProviderIds: ['provider-a', 'provider-b', 'provider-c'],
        )));

        $this->client->jsonRequest('POST', '/calculate', [
            'driver_birthday' => '1992-02-24',
            'car_type' => 'Turismo',
            'car_use' => 'Privado',
        ]);

        self::assertResponseIsSuccessful();
        $body = $this->calculateResponse();

        self::assertSame([], $body['quotes']);
        $failed = $body['meta']['failed_providers'];
        sort($failed);
        self::assertSame(['provider-a', 'provider-b', 'provider-c'], $failed);
    }

    public function testItRejectsMissingFieldsWithValidationFailedShape(): void
    {
        $this->withCampaign(FixedCampaignProvider::inactive());
        $this->withFetcher(new InMemoryQuoteFetcher(new FetchResult([], [])));

        $this->client->jsonRequest('POST', '/calculate', [
            'driver_birthday' => '1992-02-24',
            'car_use' => 'Privado',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $body = $this->problemDetailsResponse();
        self::assertSame('validation_failed', $body['error']);
        self::assertNotEmpty($body['violations']);
    }

    public function testItRejectsAFutureBirthday(): void
    {
        $this->withCampaign(FixedCampaignProvider::inactive());
        $this->withFetcher(new InMemoryQuoteFetcher(new FetchResult([], [])));

        $this->client->jsonRequest('POST', '/calculate', [
            'driver_birthday' => '2030-01-01',
            'car_type' => 'Turismo',
            'car_use' => 'Privado',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $body = $this->problemDetailsResponse();
        self::assertSame('validation_failed', $body['error']);
        self::assertSame('driver_birthday', $body['violations'][0]['field']);
    }

    public function testItRejectsAnUnderageDriver(): void
    {
        $this->withCampaign(FixedCampaignProvider::inactive());
        $this->withFetcher(new InMemoryQuoteFetcher(new FetchResult([], [])));

        $this->client->jsonRequest('POST', '/calculate', [
            'driver_birthday' => '2021-01-01',
            'car_type' => 'Turismo',
            'car_use' => 'Privado',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $body = $this->problemDetailsResponse();
        self::assertSame('validation_failed', $body['error']);
        self::assertStringContainsString('18', $body['violations'][0]['message']);
    }

    public function testItRejectsAnInvalidCarType(): void
    {
        $this->withCampaign(FixedCampaignProvider::inactive());
        $this->withFetcher(new InMemoryQuoteFetcher(new FetchResult([], [])));

        $this->client->jsonRequest('POST', '/calculate', [
            'driver_birthday' => '1992-02-24',
            'car_type' => 'Motorcycle',
            'car_use' => 'Privado',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testItAcceptsCommercialEnglishLabel(): void
    {
        $this->withCampaign(FixedCampaignProvider::inactive());
        $this->withFetcher(new InMemoryQuoteFetcher(new FetchResult(
            quotes: [new Quote('provider-a', Money::eur(365.0))],
            failedProviderIds: [],
        )));

        $this->client->jsonRequest('POST', '/calculate', [
            'driver_birthday' => '1992-02-24',
            'car_type' => 'SUV',
            'car_use' => 'Commercial',
        ]);

        self::assertResponseIsSuccessful();
        $body = $this->calculateResponse();
        self::assertCount(1, $body['quotes']);
    }

    private function withCampaign(FixedCampaignProvider $campaign): void
    {
        static::getContainer()->set(CampaignProvider::class, $campaign);
    }

    private function withFetcher(InMemoryQuoteFetcher $fetcher): void
    {
        static::getContainer()->set(QuoteFetcher::class, $fetcher);
    }

    /**
     * @return CalculateResponseShape
     */
    private function calculateResponse(): array
    {
        /** @var CalculateResponseShape */
        return json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return ProblemDetailsShape
     */
    private function problemDetailsResponse(): array
    {
        /** @var ProblemDetailsShape */
        return json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );
    }
}
