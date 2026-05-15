<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\C;

use App\Domain\Car\CarForm;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;
use App\Infrastructure\System\Clock;
use App\Infrastructure\System\RandomnessProvider;

/**
 * Provider C behaviour (PDF §1.2): 1 s baseline latency, fails ~5 % of the
 * time. CSV transport is the controller's concern; this class owns only the
 * simulated provider logic.
 */
final readonly class ProviderCSimulator
{
    private const int LATENCY_SECONDS = 1;
    private const int ERROR_PERCENT = 5;

    public function __construct(
        private ProviderCPricingService $pricing,
        private RandomnessProvider $random,
        private Clock $clock,
    ) {}

    /**
     * @return int|null Price in EUR, or null when the simulator decides to fail
     */
    public function quote(DriverAge $age, CarForm $form, CarUse $use): ?int
    {
        $this->clock->sleep(self::LATENCY_SECONDS);

        if ($this->random->intInRange(1, 100) <= self::ERROR_PERCENT) {
            return null;
        }

        return $this->pricing->priceFor($age, $form, $use);
    }
}
