<?php

declare(strict_types=1);

namespace App\Domain\Driver;

final readonly class DriverAge
{
    public const int MIN = 0;
    public const int MAX = 120;

    /**
     * Minimum age accepted by the comparison service. Below this, the
     * driver is not insurable in any of the simulated providers.
     */
    public const int MIN_INSURABLE_AGE = 18;

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

    /**
     * Domain rule: drivers must be at least {@see MIN_INSURABLE_AGE} years
     * old to receive a quote. Throws {@see UnderageDriverException} otherwise.
     *
     * Lives in the domain (not in the controller) so any future caller —
     * a CLI command, another use case, an integration test — gets the same
     * guarantee without re-implementing the check.
     */
    public function assertInsurable(): void
    {
        if ($this->value < self::MIN_INSURABLE_AGE) {
            throw new UnderageDriverException(
                \sprintf('Driver must be at least %d years old, got %d.', self::MIN_INSURABLE_AGE, $this->value),
            );
        }
    }
}
