<?php

declare(strict_types=1);

namespace App\Application\Campaign;

use App\Domain\Campaign\CampaignState;

final readonly class EnvCampaignProvider implements CampaignProvider
{
    public function __construct(
        private bool $active,
        private float $percentage,
    ) {}

    public function state(): CampaignState
    {
        return new CampaignState($this->active, $this->percentage);
    }
}
