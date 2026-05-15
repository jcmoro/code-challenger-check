<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\C;

use App\Application\Provider\QuoteProvider;
use App\Domain\Car\CarType;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;
use App\Domain\Money\Money;
use App\Domain\Quote\Quote;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class ProviderCClient implements QuoteProvider
{
    public const string PROVIDER_ID = 'provider-c';

    public function __construct(
        private HttpClientInterface $httpClient,
        private ProviderCCsvCodec $csv,
        private string $baseUrl,
    ) {}

    public function id(): string
    {
        return self::PROVIDER_ID;
    }

    public function startRequest(DriverAge $age, CarType $type, CarUse $use): ResponseInterface
    {
        $body = $this->csv->encodeRow([
            'driver_age' => $age->value,
            'car_form' => $type->toCarForm()->value,
            'car_use' => $use->value,
        ]);

        return $this->httpClient->request('POST', $this->baseUrl . '/quote', [
            'headers' => ['Content-Type' => 'text/csv'],
            'body' => $body,
            'timeout' => 12.0,
        ]);
    }

    public function parseResponse(ResponseInterface $response): ?Quote
    {
        $row = $this->csv->decodeRow($response->getContent(false));
        if (null === $row) {
            return null;
        }

        $amount = isset($row['price']) ? (float) $row['price'] : -1.0;
        $currency = $row['currency'] ?? '';

        if ($amount < 0 || '' === $currency) {
            return null;
        }

        return new Quote(self::PROVIDER_ID, new Money($amount, $currency));
    }
}
