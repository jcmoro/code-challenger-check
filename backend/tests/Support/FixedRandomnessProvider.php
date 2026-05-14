<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Infrastructure\System\RandomnessProvider;

/**
 * Scripted randomness for tests. Returns each value in `intInRange` in order;
 * once exhausted, returns the last value forever so tests don't need to know
 * exactly how many times the SUT will call it.
 */
final class FixedRandomnessProvider implements RandomnessProvider
{
    /** @var list<int> */
    private array $remaining;
    private int $lastReturned;

    public function __construct(int ...$scriptedValues)
    {
        if ([] === $scriptedValues) {
            throw new \InvalidArgumentException('FixedRandomnessProvider needs at least one scripted value.');
        }

        $this->remaining = array_values($scriptedValues);
        $this->lastReturned = end($scriptedValues);
    }

    public function intInRange(int $min, int $max): int
    {
        if ([] === $this->remaining) {
            return $this->lastReturned;
        }

        $value = array_shift($this->remaining);
        $this->lastReturned = $value;

        return $value;
    }
}
