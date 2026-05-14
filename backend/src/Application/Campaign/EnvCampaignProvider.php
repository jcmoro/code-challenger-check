<?php

declare(strict_types=1);

namespace App\Application\Campaign;

use App\Domain\Campaign\CampaignState;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class EnvCampaignProvider implements CampaignProvider
{
    public function __construct(
        #[Autowire('%env(bool:CAMPAIGN_ACTIVE)%')]
        private bool $active,
        #[Autowire('%env(float:CAMPAIGN_PERCENTAGE)%')]
        private float $percentage,
    ) {}

    public function state(): CampaignState
    {
        return new CampaignState($this->active, $this->percentage);
    }
}
