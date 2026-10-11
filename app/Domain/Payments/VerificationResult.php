<?php

namespace App\Domain\Payments;

/**
 * The verified outcome of an online payment.
 */
final readonly class VerificationResult
{
    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    /** Not finished yet, or the gateway could not be reached; try again later. */
    public const PENDING = 'pending';

    public function __construct(
        public string $status,
        public ?string $gatewayReference = null,
        public ?string $message = null,
    ) {}

    public static function paid(?string $reference): self
    {
        return new self(self::PAID, $reference);
    }

    public static function failed(string $message, string $status = self::FAILED): self
    {
        return new self($status, null, $message);
    }

    public static function pending(string $message): self
    {
        return new self(self::PENDING, null, $message);
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }
}
