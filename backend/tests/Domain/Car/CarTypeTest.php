<?php

declare(strict_types=1);

namespace App\Tests\Domain\Car;

use App\Domain\Car\CarForm;
use App\Domain\Car\CarType;
use App\Domain\Car\TipoCoche;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CarTypeTest extends TestCase
{
    /**
     * Mapping table from docs/plan/specification.md §1.2.
     */
    #[DataProvider('mappingProvider')]
    public function testItMapsCarTypeToProviderVocabularies(
        CarType $userInput,
        CarForm $expectedProviderACarForm,
        TipoCoche $expectedProviderBTipoCoche,
    ): void {
        self::assertSame($expectedProviderACarForm, $userInput->toCarForm());
        self::assertSame($expectedProviderBTipoCoche, $userInput->toTipoCoche());
    }

    /**
     * @return iterable<string, array{CarType, CarForm, TipoCoche}>
     */
    public static function mappingProvider(): iterable
    {
        yield 'Turismo → compact / turismo' => [CarType::Turismo, CarForm::Compact, TipoCoche::Turismo];
        yield 'SUV → suv / suv' => [CarType::Suv, CarForm::Suv, TipoCoche::Suv];
        yield 'Compacto → compact / compacto' => [CarType::Compacto, CarForm::Compact, TipoCoche::Compacto];
    }
}
