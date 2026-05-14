<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\B;

use App\Domain\Car\CarUse;
use App\Domain\Car\TipoCoche;
use App\Domain\Driver\DriverAge;

/**
 * Pure pricing logic for Provider B (PDF §1.2).
 *
 * base 250 €
 *   + age:     18-29 → 50, 30-59 → 20, 60+ → 100
 *   + vehicle: Turismo → 30, SUV → 200, Compacto → 0
 *
 * The PDF leaves commercial-use uplift unspecified for B. Documented assumption
 * in docs/plan/specification.md §2.3 and docs/plan/replanning.md #4: B has none.
 */
final class ProviderBPricingService
{
    private const int BASE_PRICE = 250;

    public function priceFor(DriverAge $age, TipoCoche $tipo, CarUse $use): int
    {
        // CarUse is part of the signature for symmetry with the other providers
        // and to make the no-uplift assumption visible/testable.
        unset($use);

        return self::BASE_PRICE
            + $this->ageAdjustment($age->value)
            + $this->vehicleAdjustment($tipo);
    }

    private function ageAdjustment(int $age): int
    {
        return match (true) {
            $age <= 29 => 50,
            $age <= 59 => 20,
            default => 100,
        };
    }

    private function vehicleAdjustment(TipoCoche $tipo): int
    {
        return match ($tipo) {
            TipoCoche::Turismo => 30,
            TipoCoche::Suv => 200,
            TipoCoche::Compacto => 0,
        };
    }
}
