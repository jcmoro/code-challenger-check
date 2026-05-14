<?php

declare(strict_types=1);

namespace App\Tests\UI\Http\Controller;

use App\Infrastructure\System\Clock;
use App\Infrastructure\System\RandomnessProvider;
use App\Tests\Support\FakeClock;
use App\Tests\Support\FixedRandomnessProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ProviderAControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private FakeClock $clock;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->clock = new FakeClock();

        // Set Clock once; tests will override randomness on demand.
        static::getContainer()->set(Clock::class, $this->clock);
    }

    public function testItReturnsAQuoteForAValidRequest(): void
    {
        $this->withRandomness(50); // never triggers the 10% error path

        $this->client->jsonRequest('POST', '/provider-a/quote', [
            'driver_age' => 30,
            'car_form' => 'suv',
            'car_use' => 'private',
        ]);

        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        self::assertJson($response->getContent() ?: '');

        /** @var array{price: string} $body */
        $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('317 EUR', $body['price']);
    }

    public function testItAppliesTheCommercialUplift(): void
    {
        $this->withRandomness(50);

        $this->client->jsonRequest('POST', '/provider-a/quote', [
            'driver_age' => 30,
            'car_form' => 'suv',
            'car_use' => 'commercial',
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('365 EUR', $body['price']);
    }

    public function testItSimulatesA500WhenTheRandomBucketLandsInTheErrorPercentile(): void
    {
        $this->withRandomness(5); // 5 ≤ 10 → error path

        $this->client->jsonRequest('POST', '/provider-a/quote', [
            'driver_age' => 30,
            'car_form' => 'suv',
            'car_use' => 'private',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        $body = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('provider_a_unavailable', $body['error']);
    }

    public function testItRequestsTheSpecifiedLatency(): void
    {
        $this->withRandomness(50);

        $this->client->jsonRequest('POST', '/provider-a/quote', [
            'driver_age' => 30,
            'car_form' => 'suv',
            'car_use' => 'private',
        ]);

        self::assertSame([2], $this->clock->sleeps);
    }

    public function testItRejectsAnInvalidAge(): void
    {
        $this->withRandomness(50);

        $this->client->jsonRequest('POST', '/provider-a/quote', [
            'driver_age' => 200,
            'car_form' => 'suv',
            'car_use' => 'private',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testItRejectsAnUnknownCarForm(): void
    {
        $this->withRandomness(50);

        $this->client->jsonRequest('POST', '/provider-a/quote', [
            'driver_age' => 30,
            'car_form' => 'tractor',
            'car_use' => 'private',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    private function withRandomness(int ...$scriptedValues): void
    {
        static::getContainer()->set(
            RandomnessProvider::class,
            new FixedRandomnessProvider(...$scriptedValues),
        );
    }
}
