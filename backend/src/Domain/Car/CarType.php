<?php

declare(strict_types=1);

namespace App\Domain\Car;

/**
 * Canonical, user-facing car category. The values match what the frontend sends.
 */
enum CarType: string
{
    case Turismo = 'Turismo';
    case Suv = 'SUV';
    case Compacto = 'Compacto';

    public function toCarForm(): CarForm
    {
        return match ($this) {
            self::Turismo, self::Compacto => CarForm::Compact,
            self::Suv => CarForm::Suv,
        };
    }

    public function toTipoCoche(): TipoCoche
    {
        return match ($this) {
            self::Turismo => TipoCoche::Turismo,
            self::Suv => TipoCoche::Suv,
            self::Compacto => TipoCoche::Compacto,
        };
    }
}
