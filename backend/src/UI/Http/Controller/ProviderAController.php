<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Domain\Driver\DriverAge;
use App\Infrastructure\Provider\A\ProviderAQuoteRequest;
use App\Infrastructure\Provider\A\ProviderASimulator;
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
    public function __construct(
        private ProviderASimulator $simulator,
    ) {}

    public function __invoke(#[MapRequestPayload] ProviderAQuoteRequest $request): JsonResponse
    {
        $price = $this->simulator->quote(
            new DriverAge($request->driver_age),
            $request->toCarForm(),
            $request->toCarUse(),
        );

        return null === $price
            ? new JsonResponse(['error' => 'provider_a_unavailable'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR)
            : new JsonResponse(['price' => \sprintf('%d EUR', $price)]);
    }
}
