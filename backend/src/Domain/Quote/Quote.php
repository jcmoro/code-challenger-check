<?php

declare(strict_types=1);

namespace App\Domain\Quote;

use App\Domain\Money\Money;

final readonly class Quote
{
    public function __construct(
        public string $providerId,
        public Money $price,
        public ?Money $discountedPrice = null,
    ) {
        if ('' === $providerId) {
            throw new \DomainException('Quote provider id must be a non-empty string.');
        }
    }

    public function withDiscountedPrice(Money $discounted): self
    {
        return new self($this->providerId, $this->price, $discounted);
    }

    /**
     * Effective price the customer would pay: discounted price if present, base price otherwise.
     */
    public function finalPrice(): Money
    {
        return $this->discountedPrice ?? $this->price;
    }
}
