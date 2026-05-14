<?php

declare(strict_types=1);

namespace App\Domain\Car;

/**
 * Provider B (Spanish XML) vocabulary for car use.
 */
enum UsoCoche: string
{
    case Privado = 'privado';
    case Comercial = 'comercial';

    public function toCarUse(): CarUse
    {
        return match ($this) {
            self::Privado => CarUse::Private,
            self::Comercial => CarUse::Commercial,
        };
    }
}
