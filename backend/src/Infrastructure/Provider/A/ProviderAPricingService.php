<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\A;

use App\Domain\Car\CarForm;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;

/**
 * Pure pricing logic for Provider A (PDF §1.2).
 *
 * base 217 €
 *   + age:     18-24 → 70, 25-55 → 0, 56+ → 90
 *   + vehicle: SUV → 100, Compact → 10
 *   × 1.15 if commercial use
 *   → rounded to the nearest integer EUR
 */
final class ProviderAPricingService
{
    private const int BASE_PRICE = 217;
    private const float COMMERCIAL_MULTIPLIER = 1.15;

    public function priceFor(DriverAge $age, CarForm $form, CarUse $use): int
    {
        $price = self::BASE_PRICE
            + $this->ageAdjustment($age->value)
            + $this->vehicleAdjustment($form);

        if (CarUse::Commercial === $use) {
            $price *= self::COMMERCIAL_MULTIPLIER;
        }

        return (int) round($price);
    }

    private function ageAdjustment(int $age): int
    {
        return match (true) {
            $age <= 24 => 70,
            $age <= 55 => 0,
            default => 90,
        };
    }

    private function vehicleAdjustment(CarForm $form): int
    {
        return match ($form) {
            CarForm::Suv => 100,
            CarForm::Compact => 10,
        };
    }
}
