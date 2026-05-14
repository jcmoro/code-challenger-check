<?php

declare(strict_types=1);

namespace App\Application\Campaign;

use App\Domain\Campaign\CampaignState;

interface CampaignProvider
{
    public function state(): CampaignState;
}
