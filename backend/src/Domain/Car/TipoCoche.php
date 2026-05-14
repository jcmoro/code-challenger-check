<?php

declare(strict_types=1);

namespace App\Domain\Car;

/**
 * Provider B (Spanish XML) vocabulary for the car category.
 */
enum TipoCoche: string
{
    case Turismo = 'turismo';
    case Suv = 'suv';
    case Compacto = 'compacto';
}
