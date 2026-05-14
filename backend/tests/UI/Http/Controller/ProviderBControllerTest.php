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

final class ProviderBControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private FakeClock $clock;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->clock = new FakeClock();
        static::getContainer()->set(Clock::class, $this->clock);
    }

    public function testItReturnsAnXmlQuoteForAValidSpanishRequest(): void
    {
        $this->withRandomness(50);

        $this->postXml(<<<'XML'
            <SolicitudCotizacion>
              <EdadConductor>30</EdadConductor>
              <TipoCoche>turismo</TipoCoche>
              <UsoCoche>privado</UsoCoche>
            </SolicitudCotizacion>
            XML);

        self::assertResponseIsSuccessful();
        $body = $this->responseBody();
        self::assertStringContainsString('<Precio>300.0</Precio>', $body);
        self::assertStringContainsString('<Moneda>EUR</Moneda>', $body);
        self::assertStringStartsWith('<?xml', $body);
    }

    public function testItPricesAnSuvDifferentlyFromATurismo(): void
    {
        $this->withRandomness(50);
        $this->postXml('<SolicitudCotizacion><EdadConductor>30</EdadConductor><TipoCoche>suv</TipoCoche><UsoCoche>privado</UsoCoche></SolicitudCotizacion>');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('<Precio>470.0</Precio>', $this->responseBody());
    }

    public function testItRequestsTheBaseLatency(): void
    {
        $this->withRandomness(50);

        $this->postXml('<SolicitudCotizacion><EdadConductor>30</EdadConductor><TipoCoche>turismo</TipoCoche><UsoCoche>privado</UsoCoche></SolicitudCotizacion>');

        self::assertSame([5], $this->clock->sleeps);
    }

    public function testItAddsTheOneMinuteSpikeWhenTheRandomBucketLandsAtOne(): void
    {
        $this->withRandomness(1);

        $this->postXml('<SolicitudCotizacion><EdadConductor>30</EdadConductor><TipoCoche>turismo</TipoCoche><UsoCoche>privado</UsoCoche></SolicitudCotizacion>');

        self::assertSame([5, 55], $this->clock->sleeps, 'Base 5s + spike 55s when random == 1');
    }

    public function testItReturns400OnMalformedXml(): void
    {
        $this->withRandomness(50);

        $this->postXml('<not really xml');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testItReturns400OnUnknownTipoCoche(): void
    {
        $this->withRandomness(50);

        $this->postXml('<SolicitudCotizacion><EdadConductor>30</EdadConductor><TipoCoche>furgoneta</TipoCoche><UsoCoche>privado</UsoCoche></SolicitudCotizacion>');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    /**
     * Locks the assumption documented in docs/plan/replanning.md #4 — Provider B
     * has no commercial-use uplift. The pricing service test enforces it
     * internally; this asserts it through the actual HTTP boundary.
     */
    public function testCommercialUseProducesTheSamePriceAsPrivateForProviderB(): void
    {
        $this->withRandomness(50, 50);

        $this->postXml('<SolicitudCotizacion><EdadConductor>30</EdadConductor><TipoCoche>turismo</TipoCoche><UsoCoche>privado</UsoCoche></SolicitudCotizacion>');
        self::assertResponseIsSuccessful();
        $privadoBody = $this->responseBody();

        $this->postXml('<SolicitudCotizacion><EdadConductor>30</EdadConductor><TipoCoche>turismo</TipoCoche><UsoCoche>comercial</UsoCoche></SolicitudCotizacion>');
        self::assertResponseIsSuccessful();
        $comercialBody = $this->responseBody();

        self::assertSame($privadoBody, $comercialBody, 'Provider B must price commercial == private');
    }

    private function withRandomness(int ...$scripted): void
    {
        static::getContainer()->set(
            RandomnessProvider::class,
            new FixedRandomnessProvider(...$scripted),
        );
    }

    private function postXml(string $body): void
    {
        $this->client->request(
            'POST',
            '/provider-b/quote',
            server: ['CONTENT_TYPE' => 'application/xml', 'HTTP_ACCEPT' => 'application/xml'],
            content: $body,
        );
    }

    private function responseBody(): string
    {
        return (string) $this->client->getResponse()->getContent();
    }
}
