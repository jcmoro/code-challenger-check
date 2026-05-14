<?php

declare(strict_types=1);

namespace App\Application\Provider;

final readonly class ProviderOutcome
{
    public const string OK = 'ok';
    public const string FAILED = 'failed';
    public const string TIMEOUT = 'timeout';

    public function __construct(
        public string $providerId,
        public string $outcome,
        public int $durationMs,
    ) {
        if (!\in_array($outcome, [self::OK, self::FAILED, self::TIMEOUT], true)) {
            throw new \DomainException(\sprintf('Unknown provider outcome "%s".', $outcome));
        }
    }
}
