<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Provider\C;

use App\Infrastructure\Provider\C\ProviderCCsvCodec;
use PHPUnit\Framework\TestCase;

final class ProviderCCsvCodecTest extends TestCase
{
    public function testItDecodesAValidTwoRowBody(): void
    {
        $row = (new ProviderCCsvCodec())->decodeRow("driver_age,car_form,car_use\n30,suv,private");

        self::assertSame(['driver_age' => '30', 'car_form' => 'suv', 'car_use' => 'private'], $row);
    }

    public function testItTreatsTrailingBlankLinesAsIrrelevant(): void
    {
        $row = (new ProviderCCsvCodec())->decodeRow("price,currency\n330,EUR\n\n");

        self::assertSame(['price' => '330', 'currency' => 'EUR'], $row);
    }

    public function testItReturnsNullWhenTheBodyHasOnlyAHeader(): void
    {
        self::assertNull((new ProviderCCsvCodec())->decodeRow("price,currency\n"));
    }

    public function testItReturnsNullWhenColumnsDoNotMatch(): void
    {
        self::assertNull((new ProviderCCsvCodec())->decodeRow("price,currency\n330"));
    }

    public function testItReturnsNullForAnEmptyBody(): void
    {
        self::assertNull((new ProviderCCsvCodec())->decodeRow(''));
    }

    public function testItEncodesARowWithHeaderAndDataLinesAndTrailingNewline(): void
    {
        $csv = (new ProviderCCsvCodec())->encodeRow(['price' => 330, 'currency' => 'EUR']);

        self::assertSame("price,currency\n330,EUR\n", $csv);
    }

    public function testEncodeAndDecodeRoundtripIsLossless(): void
    {
        $codec = new ProviderCCsvCodec();
        $row = ['driver_age' => '42', 'car_form' => 'compact', 'car_use' => 'commercial'];

        self::assertSame($row, $codec->decodeRow($codec->encodeRow($row)));
    }
}
