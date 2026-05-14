<?php

declare(strict_types=1);

namespace App\Tests\Application\Provider;

use App\Application\Provider\ParallelQuoteFetcher;
use App\Domain\Car\CarType;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;
use App\Infrastructure\Provider\A\ProviderAClient;
use App\Infrastructure\Provider\B\ProviderBClient;
use App\Infrastructure\Provider\C\ProviderCClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ParallelQuoteFetcherTest extends TestCase
{
    public function testItReturnsQuotesFromAllProvidersThatRespondedSuccessfully(): void
    {
        $client = $this->clientWith([
            'provider-a' => new MockResponse('{"price":"317 EUR"}', ['response_headers' => ['content-type' => 'application/json']]),
            'provider-b' => new MockResponse('<RespuestaCotizacion><Precio>300.0</Precio><Moneda>EUR</Moneda></RespuestaCotizacion>'),
            'provider-c' => new MockResponse("price,currency\n210,EUR"),
        ]);

        $fetcher = new ParallelQuoteFetcher(
            providers: $this->providers($client),
            httpClient: $client,
            timeoutSeconds: 10,
        );

        $result = $fetcher->fetchAll(new DriverAge(30), CarType::Turismo, CarUse::Private);

        self::assertCount(3, $result->quotes);
        self::assertSame([], $result->failedProviderIds);
        $ids = array_map(static fn($q) => $q->providerId, $result->quotes);
        sort($ids);
        self::assertSame(['provider-a', 'provider-b', 'provider-c'], $ids);
    }

    public function testItExcludesProvidersThatRespondedWithNon2xx(): void
    {
        $client = $this->clientWith([
            'provider-a' => new MockResponse('{"error":"provider_a_unavailable"}', ['http_code' => 500]),
            'provider-b' => new MockResponse('<RespuestaCotizacion><Precio>300.0</Precio><Moneda>EUR</Moneda></RespuestaCotizacion>'),
            'provider-c' => new MockResponse("price,currency\n210,EUR"),
        ]);

        $fetcher = new ParallelQuoteFetcher($this->providers($client), $client, 10);

        $result = $fetcher->fetchAll(new DriverAge(30), CarType::Turismo, CarUse::Private);

        self::assertCount(2, $result->quotes);
        self::assertSame(['provider-a'], $result->failedProviderIds);
    }

    public function testItExcludesProvidersWithUnparseableBodies(): void
    {
        $client = $this->clientWith([
            'provider-a' => new MockResponse('{"price":"317 EUR"}'),
            'provider-b' => new MockResponse('not actually xml'),
            'provider-c' => new MockResponse('garbage,csv'),
        ]);

        $fetcher = new ParallelQuoteFetcher($this->providers($client), $client, 10);

        $result = $fetcher->fetchAll(new DriverAge(30), CarType::Turismo, CarUse::Private);

        self::assertCount(1, $result->quotes);
        self::assertSame('provider-a', $result->quotes[0]->providerId);
        $failed = $result->failedProviderIds;
        sort($failed);
        self::assertSame(['provider-b', 'provider-c'], $failed);
    }

    public function testItExcludesProvidersThatErroredAtTransportLevel(): void
    {
        $client = $this->clientWith([
            'provider-a' => new MockResponse('', ['error' => 'connection refused']),
            'provider-b' => new MockResponse('<RespuestaCotizacion><Precio>300.0</Precio><Moneda>EUR</Moneda></RespuestaCotizacion>'),
            'provider-c' => new MockResponse('', ['error' => 'dns failed']),
        ]);

        $fetcher = new ParallelQuoteFetcher($this->providers($client), $client, 10);

        $result = $fetcher->fetchAll(new DriverAge(30), CarType::Turismo, CarUse::Private);

        self::assertCount(1, $result->quotes);
        self::assertSame('provider-b', $result->quotes[0]->providerId);
        $failed = $result->failedProviderIds;
        sort($failed);
        self::assertSame(['provider-a', 'provider-c'], $failed);
    }

    public function testItExcludesEveryProviderWhenAllFail(): void
    {
        $client = $this->clientWith([
            'provider-a' => new MockResponse('', ['http_code' => 500]),
            'provider-b' => new MockResponse('', ['http_code' => 502]),
            'provider-c' => new MockResponse('', ['http_code' => 503]),
        ]);

        $fetcher = new ParallelQuoteFetcher($this->providers($client), $client, 10);

        $result = $fetcher->fetchAll(new DriverAge(30), CarType::Turismo, CarUse::Private);

        self::assertSame([], $result->quotes);
        $failed = $result->failedProviderIds;
        sort($failed);
        self::assertSame(['provider-a', 'provider-b', 'provider-c'], $failed);
    }

    /**
     * @return list<\App\Application\Provider\QuoteProvider>
     */
    private function providers(MockHttpClient $client): array
    {
        return [
            new ProviderAClient($client, 'http://nginx/provider-a'),
            new ProviderBClient($client, 'http://nginx/provider-b'),
            new ProviderCClient($client, 'http://nginx/provider-c'),
        ];
    }

    /**
     * @param array<string, MockResponse> $byProvider keys are substrings looked up in the URL
     */
    private function clientWith(array $byProvider): MockHttpClient
    {
        return new MockHttpClient(static function (string $method, string $url) use ($byProvider): MockResponse {
            foreach ($byProvider as $needle => $response) {
                if (str_contains($url, $needle)) {
                    return $response;
                }
            }
            throw new \RuntimeException("No mock response configured for URL: {$url}");
        });
    }
}
