<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\A;

use App\Domain\Car\CarForm;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ProviderAQuoteRequest
{
    /** @var list<string> */
    public const array CAR_FORMS = ['suv', 'compact'];

    /** @var list<string> */
    public const array CAR_USES = ['private', 'commercial'];

    public function __construct(
        #[Assert\NotNull]
        #[Assert\Range(min: DriverAge::MIN, max: DriverAge::MAX)]
        public int $driver_age,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: self::CAR_FORMS)]
        public string $car_form,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: self::CAR_USES)]
        public string $car_use,
    ) {}

    public function toCarForm(): CarForm
    {
        return CarForm::from($this->car_form);
    }

    public function toCarUse(): CarUse
    {
        return CarUse::from($this->car_use);
    }
}
