<?php

declare(strict_types=1);

namespace App\Application\Provider;

use App\Domain\Car\CarType;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;

interface QuoteFetcher
{
    public function fetchAll(DriverAge $age, CarType $type, CarUse $use): FetchResult;
}
