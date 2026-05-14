<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class BootstrapTest extends TestCase
{
    public function testThePhpunitRunnerIsWiredUp(): void
    {
        $path = getenv('PATH');

        self::assertNotFalse($path, 'PATH must be available inside the test container');
    }
}
