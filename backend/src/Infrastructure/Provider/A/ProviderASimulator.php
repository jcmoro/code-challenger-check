<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\A;

use App\Domain\Car\CarForm;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;
use App\Infrastructure\System\Clock;
use App\Infrastructure\System\RandomnessProvider;

/**
 * Provider A behaviour (PDF §1.2): 2 s baseline latency, fails ~10 % of the
 * time. Encapsulates the simulated business rule so the HTTP controller stays
 * a thin format adapter.
 */
final readonly class ProviderASimulator
{
    private const int LATENCY_SECONDS = 2;
    private const int ERROR_PERCENT = 10;

    public function __construct(
        private ProviderAPricingService $pricing,
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
