<?php

declare(strict_types=1);

namespace App\Shared\Domain\RateLimit;

final readonly class Reservation
{
    private function __construct(
        public bool $granted,
        public ?int $retryAfterSeconds,
        public int $consumed,
    ) {
    }

    public static function granted(int $consumed): self
    {
        return new self(true, null, $consumed);
    }

    public static function denied(int $retryAfterSeconds): self
    {
        return new self(false, $retryAfterSeconds, 0);
    }
}
