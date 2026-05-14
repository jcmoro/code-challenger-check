<?php

declare(strict_types=1);

namespace App\UI\Http\Dto;

use App\Domain\Car\CarType;
use App\Domain\Car\CarUse;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CalculateQuoteHttpRequest
{
    /** @var list<string> */
    public const array CAR_TYPES = ['Turismo', 'SUV', 'Compacto'];

    /** @var list<string> */
    public const array CAR_USES = ['Privado', 'Comercial', 'Commercial'];

    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date(message: 'driver_birthday must be a valid ISO-8601 date (YYYY-MM-DD)')]
        public string $driver_birthday,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: self::CAR_TYPES)]
        public string $car_type,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: self::CAR_USES)]
        public string $car_use,
    ) {}

    public function toCarType(): CarType
    {
        return CarType::from($this->car_type);
    }

    public function toCarUse(): CarUse
    {
        return match ($this->car_use) {
            'Privado' => CarUse::Private,
            'Comercial', 'Commercial' => CarUse::Commercial,
            default => throw new \DomainException(\sprintf('Unknown car_use "%s".', $this->car_use)),
        };
    }
}
