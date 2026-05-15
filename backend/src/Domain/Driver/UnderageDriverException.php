<?php

declare(strict_types=1);

namespace App\Domain\Driver;

/**
 * Thrown by {@see DriverAge::assertInsurable()} when the driver is below the
 * minimum age required to be insured. Extends {@see \DomainException} so
 * existing handlers that catch domain failures keep working.
 */
final class UnderageDriverException extends \DomainException {}
