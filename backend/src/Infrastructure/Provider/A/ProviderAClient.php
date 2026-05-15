<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\A;

use App\Application\Provider\QuoteProvider;
use App\Domain\Car\CarType;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;
use App\Domain\Money\Money;
use App\Domain\Quote\Quote;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class ProviderAClient implements QuoteProvider
{
    public const string PROVIDER_ID = 'provider-a';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
    ) {}

    public function id(): string
    {
        return self::PROVIDER_ID;
    }

    public function startRequest(DriverAge $age, CarType $type, CarUse $use): ResponseInterface
    {
        return $this->httpClient->request('POST', $this->baseUrl . '/quote', [
            'json' => [
                'driver_age' => $age->value,
                'car_form' => $type->toCarForm()->value,
                'car_use' => $use->value,
            ],
            'timeout' => 12.0,
        ]);
    }

    public function parseResponse(ResponseInterface $response): ?Quote
    {
        try {
            $body = $response->toArray(false);
        } catch (\Throwable) {
            return null;
        }

        $price = $body['price'] ?? null;
        if (!\is_string($price)) {
            return null;
        }

        if (1 !== preg_match('/^(\d+(?:\.\d+)?)\s+([A-Z]{3})$/', $price, $matches)) {
            return null;
        }

        return new Quote(self::PROVIDER_ID, new Money((float) $matches[1], $matches[2]));
    }
}
