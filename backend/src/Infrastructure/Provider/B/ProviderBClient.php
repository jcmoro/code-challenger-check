<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\B;

use App\Application\Provider\QuoteProvider;
use App\Domain\Car\CarType;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;
use App\Domain\Money\Money;
use App\Domain\Quote\Quote;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class ProviderBClient implements QuoteProvider
{
    public const string PROVIDER_ID = 'provider-b';

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(PROVIDER_B_BASE_URL)%')]
        private string $baseUrl,
    ) {}

    public function id(): string
    {
        return self::PROVIDER_ID;
    }

    public function startRequest(DriverAge $age, CarType $type, CarUse $use): ResponseInterface
    {
        $body = \sprintf(
            '<SolicitudCotizacion><EdadConductor>%d</EdadConductor><TipoCoche>%s</TipoCoche><UsoCoche>%s</UsoCoche></SolicitudCotizacion>',
            $age->value,
            $type->toTipoCoche()->value,
            CarUse::Private === $use ? 'privado' : 'comercial',
        );

        return $this->httpClient->request('POST', $this->baseUrl . '/quote', [
            'headers' => ['Content-Type' => 'application/xml'],
            'body' => $body,
            'timeout' => 12.0,
        ]);
    }

    public function parseResponse(ResponseInterface $response): ?Quote
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($response->getContent(false));
        } catch (\Throwable) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (false === $xml || !isset($xml->Precio, $xml->Moneda)) {
            return null;
        }

        $amount = (float) (string) $xml->Precio;
        $currency = (string) $xml->Moneda;
        if ($amount < 0 || '' === $currency) {
            return null;
        }

        return new Quote(self::PROVIDER_ID, new Money($amount, $currency));
    }
}
