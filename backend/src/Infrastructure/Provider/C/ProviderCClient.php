<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\C;

use App\Application\Provider\QuoteProvider;
use App\Domain\Car\CarType;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;
use App\Domain\Money\Money;
use App\Domain\Quote\Quote;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class ProviderCClient implements QuoteProvider
{
    public const string PROVIDER_ID = 'provider-c';

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(PROVIDER_C_BASE_URL)%')]
        private string $baseUrl,
    ) {}

    public function id(): string
    {
        return self::PROVIDER_ID;
    }

    public function startRequest(DriverAge $age, CarType $type, CarUse $use): ResponseInterface
    {
        $body = \sprintf(
            "driver_age,car_form,car_use\n%d,%s,%s\n",
            $age->value,
            $type->toCarForm()->value,
            $use->value,
        );

        return $this->httpClient->request('POST', $this->baseUrl . '/quote', [
            'headers' => ['Content-Type' => 'text/csv'],
            'body' => $body,
            'timeout' => 12.0,
        ]);
    }

    public function parseResponse(ResponseInterface $response): ?Quote
    {
        $body = trim($response->getContent(false));
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $body)),
            static fn(string $l): bool => '' !== $l,
        ));

        if (2 !== \count($lines)) {
            return null;
        }

        $headers = array_map(
            static fn(?string $v): string => (string) $v,
            str_getcsv($lines[0], escape: '\\'),
        );
        $values = array_map(
            static fn(?string $v): string => (string) $v,
            str_getcsv($lines[1], escape: '\\'),
        );

        if (\count($headers) !== \count($values)) {
            return null;
        }

        $row = array_combine($headers, $values);
        $amount = isset($row['price']) ? (float) $row['price'] : -1.0;
        $currency = $row['currency'] ?? '';

        if ($amount < 0 || '' === $currency) {
            return null;
        }

        return new Quote(self::PROVIDER_ID, new Money($amount, $currency));
    }
}
