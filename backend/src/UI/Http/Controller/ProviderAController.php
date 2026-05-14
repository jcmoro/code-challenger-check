<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Domain\Driver\DriverAge;
use App\Infrastructure\Provider\A\ProviderAPricingService;
use App\Infrastructure\Provider\A\ProviderAQuoteRequest;
use App\Infrastructure\System\Clock;
use App\Infrastructure\System\RandomnessProvider;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/provider-a/quote', methods: ['POST'])]
#[OA\Tag(name: 'providers')]
#[OA\Response(
    response: 200,
    description: 'A price offer formatted as "<int> EUR".',
    content: new OA\JsonContent(example: ['price' => '295 EUR']),
)]
#[OA\Response(response: 500, description: 'Simulated upstream failure (10% rate).')]
final readonly class ProviderAController
{
    /**
     * Simulated unreliability rate (PDF §1.2).
     */
    private const int ERROR_PERCENT = 10;

    /**
     * Simulated latency in seconds (PDF §1.2).
     */
    private const int LATENCY_SECONDS = 2;

    public function __construct(
        private ProviderAPricingService $pricing,
        private RandomnessProvider $random,
        private Clock $clock,
    ) {}

    public function __invoke(#[MapRequestPayload] ProviderAQuoteRequest $request): JsonResponse
    {
        $this->clock->sleep(self::LATENCY_SECONDS);

        if ($this->random->intInRange(1, 100) <= self::ERROR_PERCENT) {
            return new JsonResponse(['error' => 'provider_a_unavailable'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        $price = $this->pricing->priceFor(
            new DriverAge($request->driver_age),
            $request->toCarForm(),
            $request->toCarUse(),
        );

        return new JsonResponse(['price' => \sprintf('%d EUR', $price)]);
    }
}
