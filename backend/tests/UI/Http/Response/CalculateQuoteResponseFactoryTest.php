<?php

declare(strict_types=1);

namespace App\Tests\UI\Http\Response;

use App\Application\Calculate\CalculateQuoteResult;
use App\Domain\Campaign\CampaignState;
use App\Domain\Money\Money;
use App\Domain\Quote\Quote;
use App\UI\Http\Response\CalculateQuoteResponseFactory;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-type SerializedMoney array{amount: float, currency: string}
 * @phpstan-type SerializedQuote array{provider: string, price: SerializedMoney, discounted_price: SerializedMoney|null, is_cheapest: bool}
 * @phpstan-type SerializedResponse array{
 *     campaign: array{active: bool, percentage: float},
 *     quotes: list<SerializedQuote>,
 *     meta: array{duration_ms: int, failed_providers: list<string>}
 * }
 */
final class CalculateQuoteResponseFactoryTest extends TestCase
{
    public function testItMarksTheFirstQuoteAsCheapestAndDropsItForTheRest(): void
    {
        $payload = $this->decode($this->build(
            quotes: [
                new Quote('provider-a', Money::eur(295.0)),
                new Quote('provider-b', Money::eur(310.0)),
            ],
            failed: [],
        ));

        self::assertTrue($payload['quotes'][0]['is_cheapest']);
        self::assertFalse($payload['quotes'][1]['is_cheapest']);
    }

    public function testItExposesTheDiscountedPriceWhenPresentAndNullsItOtherwise(): void
    {
        $payload = $this->decode($this->build(
            quotes: [
                (new Quote('provider-a', Money::eur(295.0)))->withDiscountedPrice(Money::eur(280.25)),
                new Quote('provider-b', Money::eur(310.0)),
            ],
            failed: [],
        ));

        self::assertSame(['amount' => 280.25, 'currency' => 'EUR'], $payload['quotes'][0]['discounted_price']);
        self::assertNull($payload['quotes'][1]['discounted_price']);
    }

    public function testItPreservesTheTrailingZeroFractionInJsonOutput(): void
    {
        // 5.0 → "5.0" (not "5") protects clients that disambiguate ints vs floats.
        $response = $this->build(
            quotes: [new Quote('provider-a', Money::eur(295.0))],
            failed: [],
            campaignActive: true,
            campaignPercentage: 5.0,
        );

        $json = (string) $response->getContent();
        self::assertStringContainsString('"percentage":5.0', $json);
        self::assertStringContainsString('"amount":295.0', $json);
    }

    public function testItRoundsTheCampaignPercentageToTwoDecimals(): void
    {
        $payload = $this->decode($this->build(
            quotes: [],
            failed: [],
            campaignActive: true,
            campaignPercentage: 5.123456,
        ));

        self::assertSame(5.12, $payload['campaign']['percentage']);
    }

    public function testItPropagatesFailedProvidersAndDurationToMeta(): void
    {
        $payload = $this->decode($this->build(
            quotes: [],
            failed: ['provider-b', 'provider-c'],
            durationMs: 4321,
        ));

        self::assertSame(['provider-b', 'provider-c'], $payload['meta']['failed_providers']);
        self::assertSame(4321, $payload['meta']['duration_ms']);
    }

    public function testEmptyQuotesProduceEmptyArrayNotNull(): void
    {
        $response = $this->build(quotes: [], failed: ['provider-a', 'provider-b', 'provider-c']);

        $json = (string) $response->getContent();
        self::assertStringContainsString('"quotes":[]', $json);
    }

    public function testItExposesTheRequestIdAsAResponseHeader(): void
    {
        $response = $this->build(quotes: [], failed: [], requestId: 'abc1234567890def');

        self::assertSame('abc1234567890def', $response->headers->get('X-Request-Id'));
    }

    /**
     * @param list<Quote> $quotes
     * @param list<string> $failed
     */
    private function build(
        array $quotes,
        array $failed,
        bool $campaignActive = false,
        float $campaignPercentage = 0.0,
        int $durationMs = 100,
        string $requestId = 'test-request-id',
    ): \Symfony\Component\HttpFoundation\JsonResponse {
        $factory = new CalculateQuoteResponseFactory();

        return $factory->fromResult(new CalculateQuoteResult(
            campaign: new CampaignState($campaignActive, $campaignPercentage),
            quotes: $quotes,
            failedProviderIds: $failed,
            durationMs: $durationMs,
            requestId: $requestId,
        ));
    }

    /**
     * @return SerializedResponse
     */
    private function decode(\Symfony\Component\HttpFoundation\JsonResponse $response): array
    {
        /** @var SerializedResponse $decoded */
        $decoded = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
