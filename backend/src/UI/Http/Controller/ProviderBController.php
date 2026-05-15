<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Domain\Car\TipoCoche;
use App\Domain\Car\UsoCoche;
use App\Domain\Driver\DriverAge;
use App\Infrastructure\Provider\B\ProviderBSimulator;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

#[Route('/provider-b/quote', methods: ['POST'])]
#[OA\Tag(name: 'providers')]
#[OA\RequestBody(
    description: 'Spanish XML quote request.',
    content: new OA\MediaType(
        mediaType: ProviderBController::CONTENT_TYPE_XML,
        example: '<SolicitudCotizacion><EdadConductor>30</EdadConductor><TipoCoche>turismo</TipoCoche><UsoCoche>privado</UsoCoche></SolicitudCotizacion>',
    ),
)]
#[OA\Response(
    response: 200,
    description: 'Quote response in Spanish XML.',
    content: new OA\MediaType(
        mediaType: ProviderBController::CONTENT_TYPE_XML,
        example: '<RespuestaCotizacion><Precio>300.0</Precio><Moneda>EUR</Moneda></RespuestaCotizacion>',
    ),
)]
final readonly class ProviderBController
{
    public const string CONTENT_TYPE_XML = 'application/xml';

    public function __construct(
        private ProviderBSimulator $simulator,
        private XmlEncoder $xml,
    ) {}

    public function __invoke(Request $request): Response
    {
        $body = trim($request->getContent());
        if ('' === $body) {
            return $this->errorResponse('empty_request', Response::HTTP_BAD_REQUEST);
        }

        try {
            /** @var array<string, scalar> $decoded */
            $decoded = $this->xml->decode($body, XmlEncoder::FORMAT);
        } catch (\Throwable) {
            return $this->errorResponse('invalid_xml', Response::HTTP_BAD_REQUEST);
        }

        $ageRaw = $decoded['EdadConductor'] ?? null;
        $tipo = TipoCoche::tryFrom((string) ($decoded['TipoCoche'] ?? ''));
        $uso = UsoCoche::tryFrom((string) ($decoded['UsoCoche'] ?? ''));

        if (null === $ageRaw || null === $tipo || null === $uso) {
            return $this->errorResponse('invalid_request', Response::HTTP_BAD_REQUEST);
        }

        try {
            $driverAge = new DriverAge((int) $ageRaw);
        } catch (\DomainException) {
            return $this->errorResponse('invalid_age', Response::HTTP_BAD_REQUEST);
        }

        $price = $this->simulator->quote($driverAge, $tipo, $uso->toCarUse());

        $xml = $this->xml->encode(
            [
                'Precio' => \sprintf('%d.0', $price),
                'Moneda' => 'EUR',
            ],
            XmlEncoder::FORMAT,
            [XmlEncoder::ROOT_NODE_NAME => 'RespuestaCotizacion'],
        );

        return new Response($xml, Response::HTTP_OK, ['Content-Type' => self::CONTENT_TYPE_XML]);
    }

    private function errorResponse(string $code, int $status): Response
    {
        $xml = $this->xml->encode(
            ['Error' => $code],
            XmlEncoder::FORMAT,
            [XmlEncoder::ROOT_NODE_NAME => 'RespuestaCotizacion'],
        );

        return new Response($xml, $status, ['Content-Type' => self::CONTENT_TYPE_XML]);
    }
}
