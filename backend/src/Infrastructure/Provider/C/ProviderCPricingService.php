<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\C;

use App\Domain\Car\CarForm;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;

/**
 * Pure pricing logic for Provider C (senior bonus, see docs/plan/specification.md §2.4).
 *
 * base 200 €
 *   + age:     18-25 → 60, 26-60 → 10, 61+ → 80
 *   + vehicle: SUV → 120, Compact → 0
 *   × 1.10 if commercial use
 *   → rounded to the nearest integer EUR
 */
final class ProviderCPricingService
{
    private const int BASE_PRICE = 200;
    private const float COMMERCIAL_MULTIPLIER = 1.10;

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
            $age <= 25 => 60,
            $age <= 60 => 10,
            default => 80,
        };
    }

    private function vehicleAdjustment(CarForm $form): int
    {
        return match ($form) {
            CarForm::Suv => 120,
            CarForm::Compact => 0,
        };
    }
}
