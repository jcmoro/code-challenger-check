<?php

declare(strict_types=1);

namespace App\Infrastructure\System;

interface Clock
{
    public function now(): \DateTimeImmutable;

    public function sleep(int $seconds): void;
}
