<?php

declare(strict_types=1);

namespace App\Application\Calculate;

use App\Domain\Campaign\CampaignState;
use App\Domain\Quote\Quote;

final readonly class CalculateQuoteResult
{
    /**
     * @param list<Quote> $quotes already sorted ascending by final price
     * @param list<string> $failedProviderIds
     * @param string $requestId 16-char hex correlation id (also surfaced as the `X-Request-Id` response header for support / debugging)
     */
    public function __construct(
        public CampaignState $campaign,
        public array $quotes,
        public array $failedProviderIds,
        public int $durationMs,
        public string $requestId,
    ) {}

    public function cheapestProviderId(): ?string
    {
        return $this->quotes[0]->providerId ?? null;
    }
}
