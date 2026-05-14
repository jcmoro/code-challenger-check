<?php

declare(strict_types=1);

namespace App\Application\Provider;

use App\Domain\Car\CarType;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;
use App\Domain\Quote\Quote;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * A two-phase contract: `startRequest()` returns a non-blocking response so the
 * fetcher can multiplex many providers in parallel; `parseResponse()` converts a
 * completed response into a domain Quote (or null when the body is unusable).
 */
#[AutoconfigureTag('app.quote_provider')]
interface QuoteProvider
{
    public function id(): string;

    public function startRequest(DriverAge $age, CarType $type, CarUse $use): ResponseInterface;

    public function parseResponse(ResponseInterface $response): ?Quote;
}
