<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Provider\A;

use App\Domain\Car\CarForm;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;
use App\Infrastructure\Provider\A\ProviderAPricingService;
use App\Infrastructure\Provider\A\ProviderASimulator;
use App\Tests\Support\FakeClock;
use App\Tests\Support\FixedRandomnessProvider;
use PHPUnit\Framework\TestCase;

final class ProviderASimulatorTest extends TestCase
{
    public function testItReturnsAPriceWhenTheRollExceedsTheErrorRate(): void
    {
        $clock = new FakeClock();
        $simulator = new ProviderASimulator(
            new ProviderAPricingService(),
            new FixedRandomnessProvider(11), // > 10 → success
            $clock,
        );

        $price = $simulator->quote(new DriverAge(30), CarForm::Compact, CarUse::Private);

        self::assertNotNull($price);
        self::assertSame([2], $clock->sleeps);
    }

    public function testItReturnsNullWhenTheRollLandsOnTheErrorRange(): void
    {
        $clock = new FakeClock();
        $simulator = new ProviderASimulator(
            new ProviderAPricingService(),
            new FixedRandomnessProvider(10), // ≤ 10 → fail
            $clock,
        );

        $price = $simulator->quote(new DriverAge(30), CarForm::Compact, CarUse::Private);

        self::assertNull($price);
        self::assertSame([2], $clock->sleeps, 'Latency is paid even on the error path.');
    }

    public function testItRejectsTheBoundaryRollsConsistently(): void
    {
        // Roll = 1 → fails (1 ≤ 10).
        $simulator = new ProviderASimulator(
            new ProviderAPricingService(),
            new FixedRandomnessProvider(1),
            new FakeClock(),
        );
        self::assertNull($simulator->quote(new DriverAge(30), CarForm::Compact, CarUse::Private));
    }
}
