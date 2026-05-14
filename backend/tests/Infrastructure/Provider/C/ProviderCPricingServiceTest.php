<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Provider\C;

use App\Domain\Car\CarForm;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;
use App\Infrastructure\Provider\C\ProviderCPricingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cases mirror validation.md §3.3.
 *
 * Formula: round((200 + ageAdj + vehicleAdj) × commercialMultiplier).
 */
final class ProviderCPricingServiceTest extends TestCase
{
    private ProviderCPricingService $service;

    protected function setUp(): void
    {
        $this->service = new ProviderCPricingService();
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
        // Age bracket boundaries — SUV private (base 200 + suv 120 = 320 base).
        yield '18 / SUV / private → 380 (low age starts)' => [18, CarForm::Suv, CarUse::Private, 380];
        yield '25 / SUV / private → 380 (low age ends)' => [25, CarForm::Suv, CarUse::Private, 380];
        yield '26 / SUV / private → 330 (mid bracket starts)' => [26, CarForm::Suv, CarUse::Private, 330];
        yield '60 / SUV / private → 330 (mid bracket ends)' => [60, CarForm::Suv, CarUse::Private, 330];
        yield '61 / SUV / private → 400 (high age starts)' => [61, CarForm::Suv, CarUse::Private, 400];

        // Compact at age 30.
        yield '30 / compact / private → 210' => [30, CarForm::Compact, CarUse::Private, 210];

        // Cases where C is the cheapest (exercises validation.md §3.3 requirement).
        yield '22 / compact / private → 260 (C beats A=297 and B=330)' => [22, CarForm::Compact, CarUse::Private, 260];

        // Commercial uplift (×1.10).
        yield '30 / SUV / commercial → 363 (round(330×1.10))' => [30, CarForm::Suv, CarUse::Commercial, 363];
        yield '30 / compact / commercial → 231 (round(210×1.10))' => [30, CarForm::Compact, CarUse::Commercial, 231];
    }
}
