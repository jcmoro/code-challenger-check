<?php

declare(strict_types=1);

namespace App\Domain\Driver;

final readonly class DriverAge
{
    public const int MIN = 0;
    public const int MAX = 120;

    public function __construct(public int $value)
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw new \DomainException(
                \sprintf('Driver age must be between %d and %d, got %d.', self::MIN, self::MAX, $value),
            );
        }
    }

    public static function fromBirthday(\DateTimeImmutable $birthday, \DateTimeImmutable $today): self
    {
        if ($birthday > $today) {
            throw new \DomainException('Birthday cannot be in the future.');
        }

        return new self($today->diff($birthday)->y);
    }
}
