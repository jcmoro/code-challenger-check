<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Provider\A;

use App\Domain\Car\CarForm;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;
use App\Infrastructure\Provider\A\ProviderAPricingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cases mirror validation.md §3.1.
 *
 * Formula: round((217 + ageAdj + vehicleAdj) × commercialMultiplier).
 */
final class ProviderAPricingServiceTest extends TestCase
{
    private ProviderAPricingService $service;

    protected function setUp(): void
    {
        $this->service = new ProviderAPricingService();
    }

    #[DataProvider('pricingTableProvider')]
    public function testItComputesThePriceForEveryBracket(
        int $age,
        CarForm $form,
        CarUse $use,
        int $expectedPriceEur,
    ): void {
        $actual = $this->service->priceFor(new DriverAge($age), $form, $use);

        self::assertSame($expectedPriceEur, $actual);
    }

    /**
     * @return iterable<string, array{int, CarForm, CarUse, int}>
     */
    public static function pricingTableProvider(): iterable
    {
        // Age boundaries — SUV private (base 217 + suv 100 = 317).
        yield '18 / SUV / private → 387 (low age boundary)' => [18, CarForm::Suv, CarUse::Private, 387];
        yield '24 / SUV / private → 387 (low age boundary)' => [24, CarForm::Suv, CarUse::Private, 387];
        yield '25 / SUV / private → 317 (mid bracket starts)' => [25, CarForm::Suv, CarUse::Private, 317];
        yield '55 / SUV / private → 317 (mid bracket ends)' => [55, CarForm::Suv, CarUse::Private, 317];
        yield '56 / SUV / private → 407 (high age starts)' => [56, CarForm::Suv, CarUse::Private, 407];
        yield '120 / SUV / private → 407 (extreme age)' => [120, CarForm::Suv, CarUse::Private, 407];

        // Compact vehicle.
        yield '30 / compact / private → 227' => [30, CarForm::Compact, CarUse::Private, 227];
        yield '22 / compact / private → 297 (low age + compact)' => [22, CarForm::Compact, CarUse::Private, 297];

        // Commercial uplift (×1.15 applied last).
        yield '30 / SUV / commercial → 365 (round(317×1.15))' => [30, CarForm::Suv, CarUse::Commercial, 365];
        yield '30 / compact / commercial → 261 (round(227×1.15))' => [30, CarForm::Compact, CarUse::Commercial, 261];
    }
}
