<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Provider\FetchResult;
use App\Application\Provider\QuoteFetcher;
use App\Domain\Car\CarType;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;

/**
 * Returns a pre-configured FetchResult for tests, ignoring the inputs.
 * Also records the last call so tests can assert handlers passed correct args.
 */
final class InMemoryQuoteFetcher implements QuoteFetcher
{
    public ?DriverAge $lastAge = null;
    public ?CarType $lastType = null;
    public ?CarUse $lastUse = null;

    public function __construct(private readonly FetchResult $result) {}

    public function fetchAll(DriverAge $age, CarType $type, CarUse $use): FetchResult
    {
        $this->lastAge = $age;
        $this->lastType = $type;
        $this->lastUse = $use;

        return $this->result;
    }
}
