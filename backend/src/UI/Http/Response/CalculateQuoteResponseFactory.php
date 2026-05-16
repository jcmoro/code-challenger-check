<?php

declare(strict_types=1);

namespace App\UI\Http\Response;

use App\Application\Calculate\CalculateQuoteResult;
use App\Domain\Money\Money;
use App\Domain\Quote\Quote;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Owns the wire format of the /calculate 200 response. Kept out of the
 * controller so the latter handles only the HTTP plumbing (parse → invoke
 * handler → translate result/error → return). The JSON_PRESERVE_ZERO_FRACTION
 * flag preserves trailing ".0" on whole-euro amounts so 5.0 doesn't degrade
 * to 5 (per the JSON contract in docs/specs/openapi/v1).
 */
final readonly class CalculateQuoteResponseFactory
{
    public function fromResult(CalculateQuoteResult $result): JsonResponse
    {
        $response = new JsonResponse();
        $response->setEncodingOptions($response->getEncodingOptions() | \JSON_PRESERVE_ZERO_FRACTION);
        $response->setData($this->serializeResult($result));
        $response->headers->set('X-Request-Id', $result->requestId);

        return $response;
    }

    /**
     * @return array{
     *     campaign: array{active: bool, percentage: float},
     *     quotes: list<array{provider: string, price: array{amount: float, currency: string}, discounted_price: array{amount: float, currency: string}|null, is_cheapest: bool}>,
     *     meta: array{duration_ms: int, failed_providers: list<string>}
     * }
     */
    private function serializeResult(CalculateQuoteResult $result): array
    {
        $cheapestId = $result->cheapestProviderId();

        return [
            'campaign' => [
                'active' => $result->campaign->active,
                'percentage' => round($result->campaign->percentage, 2),
            ],
            'quotes' => array_map(
                fn(Quote $q): array => $this->serializeQuote($q, $cheapestId),
                $result->quotes,
            ),
            'meta' => [
                'duration_ms' => $result->durationMs,
                'failed_providers' => $result->failedProviderIds,
            ],
        ];
    }

    /**
     * @return array{provider: string, price: array{amount: float, currency: string}, discounted_price: array{amount: float, currency: string}|null, is_cheapest: bool}
     */
    private function serializeQuote(Quote $quote, ?string $cheapestId): array
    {
        return [
            'provider' => $quote->providerId,
            'price' => $this->serializeMoney($quote->price->rounded()),
            'discounted_price' => null !== $quote->discountedPrice
                ? $this->serializeMoney($quote->discountedPrice->rounded())
                : null,
            'is_cheapest' => $quote->providerId === $cheapestId,
        ];
    }

    /**
     * @return array{amount: float, currency: string}
     */
    private function serializeMoney(Money $money): array
    {
        return ['amount' => $money->amount, 'currency' => $money->currency];
    }
}
