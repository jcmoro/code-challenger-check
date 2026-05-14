<?php

declare(strict_types=1);

namespace App\Application\Calculate;

use App\Domain\Car\CarType;
use App\Domain\Car\CarUse;

final readonly class CalculateQuoteCommand
{
    public function __construct(
        public \DateTimeImmutable $driverBirthday,
        public CarType $carType,
        public CarUse $carUse,
    ) {}
}
