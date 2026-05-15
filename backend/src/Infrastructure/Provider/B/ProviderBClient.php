<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\B;

use App\Application\Provider\QuoteProvider;
use App\Domain\Car\CarType;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;
use App\Domain\Money\Money;
use App\Domain\Quote\Quote;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class ProviderBClient implements QuoteProvider
{
    public const string PROVIDER_ID = 'provider-b';

    public function __construct(
        private HttpClientInterface $httpClient,
        private XmlEncoder $xml,
        private string $baseUrl,
    ) {}

    public function id(): string
    {
        return self::PROVIDER_ID;
    }

    public function startRequest(DriverAge $age, CarType $type, CarUse $use): ResponseInterface
    {
        $body = $this->xml->encode(
            [
                'EdadConductor' => $age->value,
                'TipoCoche' => $type->toTipoCoche()->value,
                'UsoCoche' => CarUse::Private === $use ? 'privado' : 'comercial',
            ],
            XmlEncoder::FORMAT,
            [XmlEncoder::ROOT_NODE_NAME => 'SolicitudCotizacion'],
        );

        return $this->httpClient->request('POST', $this->baseUrl . '/quote', [
            'headers' => ['Content-Type' => 'application/xml'],
            'body' => $body,
            'timeout' => 12.0,
        ]);
    }

    public function parseResponse(ResponseInterface $response): ?Quote
    {
        try {
            /** @var array<string, scalar> $decoded */
            $decoded = $this->xml->decode($response->getContent(false), XmlEncoder::FORMAT);
        } catch (NotEncodableValueException) {
            return null;
        }

        if (!isset($decoded['Precio'], $decoded['Moneda'])) {
            return null;
        }

        $amount = (float) $decoded['Precio'];
        $currency = (string) $decoded['Moneda'];

        if ($amount < 0 || '' === $currency) {
            return null;
        }

        return new Quote(self::PROVIDER_ID, new Money($amount, $currency));
    }
}
