<?php

declare(strict_types=1);

namespace App\Tests\Domain\Driver;

use App\Domain\Driver\DriverAge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DriverAgeTest extends TestCase
{
    public function testItAcceptsTheBoundaryValues(): void
    {
        self::assertSame(0, (new DriverAge(0))->value);
        self::assertSame(120, (new DriverAge(120))->value);
    }

    #[DataProvider('invalidAgeProvider')]
    public function testItRejectsOutOfRangeAges(int $invalidAge): void
    {
        self::expectException(\DomainException::class);

        new DriverAge($invalidAge);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidAgeProvider(): iterable
    {
        yield 'below minimum' => [-1];
        yield 'above maximum' => [121];
        yield 'extremely negative' => [-100];
    }

    public function testItComputesAgeFromBirthdayBeforeBirthdayThisYear(): void
    {
        // Today 2026-05-13, birthday 1992-06-01 → birthday not yet passed → age 33.
        $today = new \DateTimeImmutable('2026-05-13');
        $birthday = new \DateTimeImmutable('1992-06-01');

        self::assertSame(33, DriverAge::fromBirthday($birthday, $today)->value);
    }

    public function testItComputesAgeFromBirthdayAfterBirthdayThisYear(): void
    {
        // Today 2026-05-13, birthday 1992-02-24 → birthday passed → age 34.
        $today = new \DateTimeImmutable('2026-05-13');
        $birthday = new \DateTimeImmutable('1992-02-24');

        self::assertSame(34, DriverAge::fromBirthday($birthday, $today)->value);
    }

    public function testItComputesAgeFromBirthdayOnTheBirthday(): void
    {
        $today = new \DateTimeImmutable('2026-05-13');
        $birthday = new \DateTimeImmutable('1992-05-13');

        self::assertSame(34, DriverAge::fromBirthday($birthday, $today)->value);
    }

    public function testItRejectsBirthdaysInTheFuture(): void
    {
        self::expectException(\DomainException::class);

        DriverAge::fromBirthday(
            new \DateTimeImmutable('2030-01-01'),
            new \DateTimeImmutable('2026-05-13'),
        );
    }
}
