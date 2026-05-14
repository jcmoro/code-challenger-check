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

final class ProviderCControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private FakeClock $clock;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->clock = new FakeClock();
        static::getContainer()->set(Clock::class, $this->clock);
    }

    public function testItReturnsACsvQuoteForAValidRequest(): void
    {
        $this->withRandomness(50);

        $this->postCsv("driver_age,car_form,car_use\n30,suv,private\n");

        self::assertResponseIsSuccessful();
        self::assertSame("price,currency\n330,EUR\n", $this->responseBody());
    }

    public function testItAppliesTheCommercialUplift(): void
    {
        $this->withRandomness(50);

        $this->postCsv("driver_age,car_form,car_use\n30,suv,commercial\n");

        self::assertResponseIsSuccessful();
        self::assertSame("price,currency\n363,EUR\n", $this->responseBody());
    }

    public function testItSimulatesA503WhenTheRandomBucketLandsInTheErrorPercentile(): void
    {
        $this->withRandomness(3); // 3 ≤ 5 → error

        $this->postCsv("driver_age,car_form,car_use\n30,suv,private\n");

        self::assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);
    }

    public function testItRequestsTheSpecifiedLatency(): void
    {
        $this->withRandomness(50);

        $this->postCsv("driver_age,car_form,car_use\n30,suv,private\n");

        self::assertSame([1], $this->clock->sleeps);
    }

    public function testItReturns400OnMalformedCsv(): void
    {
        $this->withRandomness(50);

        $this->postCsv('not really csv');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testItReturns400OnUnknownCarForm(): void
    {
        $this->withRandomness(50);

        $this->postCsv("driver_age,car_form,car_use\n30,tractor,private\n");

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    private function withRandomness(int ...$scripted): void
    {
        static::getContainer()->set(
            RandomnessProvider::class,
            new FixedRandomnessProvider(...$scripted),
        );
    }

    private function postCsv(string $body): void
    {
        $this->client->request(
            'POST',
            '/provider-c/quote',
            server: ['CONTENT_TYPE' => 'text/csv'],
            content: $body,
        );
    }

    private function responseBody(): string
    {
        return (string) $this->client->getResponse()->getContent();
    }
}
