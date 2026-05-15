<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\B;

use App\Domain\Car\CarUse;
use App\Domain\Car\TipoCoche;
use App\Domain\Driver\DriverAge;
use App\Infrastructure\System\Clock;
use App\Infrastructure\System\RandomnessProvider;

/**
 * Provider B behaviour (PDF §1.2): 5 s baseline latency, plus a 1 % chance
 * of an extra 55 s spike — the timeout-trigger scenario the parallel fetcher
 * has to cope with. Always returns a price; never injects errors.
 */
final readonly class ProviderBSimulator
{
    private const int LATENCY_SECONDS = 5;
    private const int EXTRA_LATENCY_SECONDS = 55;
    private const int EXTRA_LATENCY_PERCENT = 1;

    public function __construct(
        private ProviderBPricingService $pricing,
        private RandomnessProvider $random,
        private Clock $clock,
    ) {}

    public function quote(DriverAge $age, TipoCoche $tipo, CarUse $use): int
    {
        $this->clock->sleep(self::LATENCY_SECONDS);

        if ($this->random->intInRange(1, 100) <= self::EXTRA_LATENCY_PERCENT) {
            $this->clock->sleep(self::EXTRA_LATENCY_SECONDS);
        }

        return $this->pricing->priceFor($age, $tipo, $use);
    }
}
