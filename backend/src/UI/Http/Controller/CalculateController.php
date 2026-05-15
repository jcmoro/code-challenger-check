<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Application\Calculate\CalculateQuoteCommand;
use App\Application\Calculate\CalculateQuoteHandler;
use App\UI\Http\Dto\CalculateQuoteHttpRequest;
use App\UI\Http\Response\CalculateQuoteResponseFactory;
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
        private CalculateQuoteResponseFactory $responseFactory,
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
            return $this->validationError('driver_birthday', $e->getMessage());
        }

        return $this->responseFactory->fromResult($result);
    }

    private function validationError(string $field, string $message): JsonResponse
    {
        return new JsonResponse([
            'error' => 'validation_failed',
            'violations' => [['field' => $field, 'message' => $message]],
        ], JsonResponse::HTTP_BAD_REQUEST);
    }
}
