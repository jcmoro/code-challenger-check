<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Infrastructure\System\Clock;

/**
 * Test clock. `sleep()` records the request without actually sleeping —
 * tests assert against the `sleeps` log instead of waiting.
 */
final class FakeClock implements Clock
{
    /** @var list<int> */
    public array $sleeps = [];

    public function __construct(
        private readonly \DateTimeImmutable $fixedNow = new \DateTimeImmutable('2026-05-13'),
    ) {}

    public function now(): \DateTimeImmutable
    {
        return $this->fixedNow;
    }

    public function sleep(int $seconds): void
    {
        $this->sleeps[] = $seconds;
    }

    public function totalSlept(): int
    {
        return array_sum($this->sleeps);
    }
}
