<?php

declare(strict_types=1);

namespace App\Infrastructure\System;

interface RandomnessProvider
{
    /**
     * Inclusive integer in [$min, $max].
     */
    public function intInRange(int $min, int $max): int;
}
