<?php

declare(strict_types=1);

namespace App\Infrastructure\System;

final class MtRandomnessProvider implements RandomnessProvider
{
    public function intInRange(int $min, int $max): int
    {
        return random_int($min, $max);
    }
}
