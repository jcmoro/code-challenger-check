<?php

declare(strict_types=1);

namespace App\Application\Provider;

use App\Domain\Quote\Quote;

final readonly class FetchResult
{
    /**
     * @param list<Quote> $quotes
     * @param list<string> $failedProviderIds
     * @param array<string, ProviderOutcome> $outcomes keyed by provider id, includes both ok and failed
     */
    public function __construct(
        public array $quotes,
        public array $failedProviderIds,
        public array $outcomes = [],
    ) {}
}
