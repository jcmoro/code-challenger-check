<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Provider\B;

use App\Domain\Car\CarUse;
use App\Domain\Car\TipoCoche;
use App\Domain\Driver\DriverAge;
use App\Infrastructure\Provider\B\ProviderBPricingService;
use App\Infrastructure\Provider\B\ProviderBSimulator;
use App\Tests\Support\FakeClock;
use App\Tests\Support\FixedRandomnessProvider;
use PHPUnit\Framework\TestCase;

final class ProviderBSimulatorTest extends TestCase
{
    public function testItPaysOnlyTheBaseLatencyOnTheLuckyPath(): void
    {
        $clock = new FakeClock();
        $simulator = new ProviderBSimulator(
            new ProviderBPricingService(),
            new FixedRandomnessProvider(2), // > 1 → no extra spike
            $clock,
        );

        $price = $simulator->quote(new DriverAge(30), TipoCoche::Turismo, CarUse::Private);

        self::assertGreaterThan(0, $price);
        self::assertSame([5], $clock->sleeps);
    }

    public function testItPaysTheExtraSpikeWhenTheUnluckyRollHits(): void
    {
        $clock = new FakeClock();
        $simulator = new ProviderBSimulator(
            new ProviderBPricingService(),
            new FixedRandomnessProvider(1), // ≤ 1 → extra spike
            $clock,
        );

        $price = $simulator->quote(new DriverAge(30), TipoCoche::Turismo, CarUse::Private);

        self::assertGreaterThan(0, $price);
        self::assertSame([5, 55], $clock->sleeps, 'Spike adds 55 s on top of the 5 s baseline.');
    }
}
