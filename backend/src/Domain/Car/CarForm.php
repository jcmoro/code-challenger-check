<?php

declare(strict_types=1);

namespace App\Domain\Car;

/**
 * Provider A & C vocabulary. Provider A collapses "Turismo" into "compact"
 * (see PDF §1.2): this enum reflects what those providers actually receive.
 */
enum CarForm: string
{
    case Suv = 'suv';
    case Compact = 'compact';
}
