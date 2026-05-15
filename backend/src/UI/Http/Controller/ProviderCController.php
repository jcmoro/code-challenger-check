<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Domain\Car\CarForm;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;
use App\Infrastructure\Provider\C\ProviderCCsvCodec;
use App\Infrastructure\Provider\C\ProviderCSimulator;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/provider-c/quote', methods: ['POST'])]
#[OA\Tag(name: 'providers')]
#[OA\RequestBody(
    description: 'CSV quote request with one data row.',
    content: new OA\MediaType(mediaType: 'text/csv', example: "driver_age,car_form,car_use\n30,suv,private"),
)]
#[OA\Response(
    response: 200,
    description: 'Quote response as CSV.',
    content: new OA\MediaType(mediaType: 'text/csv', example: "price,currency\n330,EUR"),
)]
#[OA\Response(response: 503, description: 'Simulated upstream failure (5% rate).')]
final readonly class ProviderCController
{
    private const string CONTENT_TYPE = 'text/csv; charset=UTF-8';

    public function __construct(
        private ProviderCSimulator $simulator,
        private ProviderCCsvCodec $csv,
    ) {}

    public function __invoke(Request $request): Response
    {
        $data = $this->csv->decodeRow($request->getContent());
        if (null === $data) {
            return $this->error('invalid_csv', Response::HTTP_BAD_REQUEST);
        }

        $form = CarForm::tryFrom($data['car_form'] ?? '');
        $use = CarUse::tryFrom($data['car_use'] ?? '');
        if (null === $form || null === $use) {
            return $this->error('invalid_request', Response::HTTP_BAD_REQUEST);
        }

        try {
            $age = new DriverAge((int) ($data['driver_age'] ?? -1));
        } catch (\DomainException) {
            return $this->error('invalid_age', Response::HTTP_BAD_REQUEST);
        }

        $price = $this->simulator->quote($age, $form, $use);
        if (null === $price) {
            return $this->error('provider_c_unavailable', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new Response(
            $this->csv->encodeRow(['price' => $price, 'currency' => 'EUR']),
            Response::HTTP_OK,
            ['Content-Type' => self::CONTENT_TYPE],
        );
    }

    private function error(string $code, int $status): Response
    {
        return new Response(
            $this->csv->encodeRow(['error' => $code]),
            $status,
            ['Content-Type' => self::CONTENT_TYPE],
        );
    }
}
