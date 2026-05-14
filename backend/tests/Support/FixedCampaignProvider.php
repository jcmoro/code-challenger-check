<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Campaign\CampaignProvider;
use App\Domain\Campaign\CampaignState;

final class FixedCampaignProvider implements CampaignProvider
{
    public function __construct(private readonly CampaignState $state) {}

    public static function active(float $percentage = 5.0): self
    {
        return new self(new CampaignState(true, $percentage));
    }

    public static function inactive(): self
    {
        return new self(CampaignState::inactive());
    }

    public function state(): CampaignState
    {
        return $this->state;
    }
}
