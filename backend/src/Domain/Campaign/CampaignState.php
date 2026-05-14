<?php

declare(strict_types=1);

namespace App\Domain\Campaign;

final readonly class CampaignState
{
    public function __construct(
        public bool $active,
        public float $percentage,
    ) {
        if ($percentage < 0.0 || $percentage > 100.0) {
            throw new \DomainException(\sprintf('Campaign percentage must be in [0, 100], got %F.', $percentage));
        }
    }

    public static function inactive(): self
    {
        return new self(false, 0.0);
    }

    /**
     * The multiplier the customer actually pays, e.g. 0.95 for a 5% campaign.
     */
    public function customerPaysMultiplier(): float
    {
        return (100.0 - $this->percentage) / 100.0;
    }
}
