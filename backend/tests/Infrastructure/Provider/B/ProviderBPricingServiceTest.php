<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Provider\B;

use App\Domain\Car\CarUse;
use App\Domain\Car\TipoCoche;
use App\Domain\Driver\DriverAge;
use App\Infrastructure\Provider\B\ProviderBPricingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cases mirror validation.md §3.2.
 *
 * Formula: 250 + ageAdj + vehicleAdj (no commercial uplift — see replanning #4).
 */
final class ProviderBPricingServiceTest extends TestCase
{
    private ProviderBPricingService $service;

    protected function setUp(): void
    {
        $this->service = new ProviderBPricingService();
    }

    #[DataProvider('pricingTableProvider')]
    public function testItComputesThePriceForEveryBracket(
        int $age,
        TipoCoche $tipo,
        CarUse $use,
        int $expectedPriceEur,
    ): void {
        $actual = $this->service->priceFor(new DriverAge($age), $tipo, $use);

        self::assertSame($expectedPriceEur, $actual);
    }

    /**
     * @return iterable<string, array{int, TipoCoche, CarUse, int}>
     */
    public static function pricingTableProvider(): iterable
    {
        // Age boundaries — Turismo private (250 + 30 = 280 base).
        yield '18 / turismo / privado → 330 (low age boundary)' => [18, TipoCoche::Turismo, CarUse::Private, 330];
        yield '29 / turismo / privado → 330 (low age ends)' => [29, TipoCoche::Turismo, CarUse::Private, 330];
        yield '30 / turismo / privado → 300 (mid bracket starts)' => [30, TipoCoche::Turismo, CarUse::Private, 300];
        yield '59 / turismo / privado → 300 (mid bracket ends)' => [59, TipoCoche::Turismo, CarUse::Private, 300];
        yield '60 / turismo / privado → 380 (high age starts)' => [60, TipoCoche::Turismo, CarUse::Private, 380];

        // Other vehicle types at age 30.
        yield '30 / suv / privado → 470' => [30, TipoCoche::Suv, CarUse::Private, 470];
        yield '30 / compacto / privado → 270' => [30, TipoCoche::Compacto, CarUse::Private, 270];
    }

    public function testCommercialUseDoesNotChangeTheBPrice(): void
    {
        // Locks in the assumption documented in replanning #4: B has no commercial uplift.
        // If this fails, the assumption was wrong — update spec + replanning, not the test
        // silently.
        $private = $this->service->priceFor(new DriverAge(30), TipoCoche::Turismo, CarUse::Private);
        $commercial = $this->service->priceFor(new DriverAge(30), TipoCoche::Turismo, CarUse::Commercial);

        self::assertSame($private, $commercial);
    }
}
