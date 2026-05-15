<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Application\Calculate\CalculateQuoteCommand;
use App\Application\Calculate\CalculateQuoteHandler;
use App\Application\Calculate\CalculateQuoteResult;
use App\Domain\Money\Money;
use App\Domain\Quote\Quote;
use App\UI\Http\Dto\CalculateQuoteHttpRequest;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/calculate', methods: ['POST'])]
#[OA\Tag(name: 'calculate')]
#[OA\Response(
    response: 200,
    description: 'Aggregated, sorted quotes from the surviving providers.',
    content: new OA\JsonContent(
        example: [
            'campaign' => ['active' => true, 'percentage' => 5.0],
            'quotes' => [
                [
                    'provider' => 'provider-c',
                    'price' => ['amount' => 210.0, 'currency' => 'EUR'],
                    'discounted_price' => ['amount' => 199.5, 'currency' => 'EUR'],
                    'is_cheapest' => true,
                ],
                [
                    'provider' => 'provider-a',
                    'price' => ['amount' => 317.0, 'currency' => 'EUR'],
                    'discounted_price' => ['amount' => 301.15, 'currency' => 'EUR'],
                    'is_cheapest' => false,
                ],
            ],
            'meta' => ['duration_ms' => 5132, 'failed_providers' => ['provider-b']],
        ],
    ),
)]
#[OA\Response(
    response: 400,
    description: 'Validation failed.',
    content: new OA\JsonContent(
        example: [
            'error' => 'validation_failed',
            'violations' => [['field' => 'driver_birthday', 'message' => 'driver must be at least 18 years old']],
        ],
    ),
)]
final readonly class CalculateController
{
    public function __construct(
        private CalculateQuoteHandler $handler,
    ) {}

    public function __invoke(#[MapRequestPayload] CalculateQuoteHttpRequest $request): JsonResponse
    {
        try {
            $result = $this->handler->handle(new CalculateQuoteCommand(
                driverBirthday: new \DateTimeImmutable($request->driver_birthday),
                carType: $request->toCarType(),
                carUse: $request->toCarUse(),
            ));
        } catch (\DomainException $e) {
            // The handler/domain enforce birthday validity (future date, age
            // out of range, under-{@see DriverAge::MIN_INSURABLE_AGE}). All
            // surface here as a single 400 with the same structured envelope.
            return $this->validationError('driver_birthday', $e->getMessage());
        }

        $response = new JsonResponse();
        $response->setEncodingOptions($response->getEncodingOptions() | \JSON_PRESERVE_ZERO_FRACTION);
        $response->setData($this->serializeResult($result));

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

    private function validationError(string $field, string $message): JsonResponse
    {
        return new JsonResponse([
            'error' => 'validation_failed',
            'violations' => [['field' => $field, 'message' => $message]],
        ], JsonResponse::HTTP_BAD_REQUEST);
    }
}
