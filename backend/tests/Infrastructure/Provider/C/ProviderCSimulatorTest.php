<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Provider\C;

use App\Domain\Car\CarForm;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;
use App\Infrastructure\Provider\C\ProviderCPricingService;
use App\Infrastructure\Provider\C\ProviderCSimulator;
use App\Tests\Support\FakeClock;
use App\Tests\Support\FixedRandomnessProvider;
use PHPUnit\Framework\TestCase;

final class ProviderCSimulatorTest extends TestCase
{
    public function testItReturnsAPriceWhenTheRollExceedsTheErrorRate(): void
    {
        $clock = new FakeClock();
        $simulator = new ProviderCSimulator(
            new ProviderCPricingService(),
            new FixedRandomnessProvider(6), // > 5 → success
            $clock,
        );

        $price = $simulator->quote(new DriverAge(30), CarForm::Suv, CarUse::Private);

        self::assertNotNull($price);
        self::assertSame([1], $clock->sleeps);
    }

    public function testItReturnsNullWhenTheRollLandsOnTheErrorRange(): void
    {
        $clock = new FakeClock();
        $simulator = new ProviderCSimulator(
            new ProviderCPricingService(),
            new FixedRandomnessProvider(5), // ≤ 5 → fail
            $clock,
        );

        $price = $simulator->quote(new DriverAge(30), CarForm::Suv, CarUse::Private);

        self::assertNull($price);
        self::assertSame([1], $clock->sleeps, 'Latency is paid even on the error path.');
    }

    public function testItRejectsTheBoundaryRollsConsistently(): void
    {
        $simulator = new ProviderCSimulator(
            new ProviderCPricingService(),
            new FixedRandomnessProvider(1),
            new FakeClock(),
        );
        self::assertNull($simulator->quote(new DriverAge(30), CarForm::Suv, CarUse::Private));
    }
}
