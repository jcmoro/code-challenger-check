<?php

declare(strict_types=1);

namespace App\Domain\Money;

final readonly class Money
{
    public const string DEFAULT_CURRENCY = 'EUR';

    public function __construct(
        public float $amount,
        public string $currency = self::DEFAULT_CURRENCY,
    ) {
        if ($amount < 0) {
            throw new \DomainException(\sprintf('Money amount must be non-negative, got %F.', $amount));
        }
        if ('' === $currency) {
            throw new \DomainException('Money currency must be a non-empty ISO-4217 code.');
        }
    }

    public static function eur(float $amount): self
    {
        return new self($amount, self::DEFAULT_CURRENCY);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function multiply(float $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }

    public function rounded(int $decimals = 2): self
    {
        return new self(round($this->amount, $decimals), $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency
            && abs($this->amount - $other->amount) < 0.005;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \DomainException(
                \sprintf('Cannot operate on Money with different currencies: %s vs %s.', $this->currency, $other->currency),
            );
        }
    }
}
