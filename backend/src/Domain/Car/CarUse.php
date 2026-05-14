<?php

declare(strict_types=1);

namespace App\Domain\Car;

enum CarUse: string
{
    case Private = 'private';
    case Commercial = 'commercial';
}
