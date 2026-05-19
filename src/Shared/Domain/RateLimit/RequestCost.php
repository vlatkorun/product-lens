<?php

declare(strict_types=1);

namespace App\Shared\Domain\RateLimit;

final readonly class RequestCost
{
    public function __construct(
        public int $requested,
        public ?int $actual,
    ) {
    }

    public function overcharge(): int
    {
        if ($this->actual === null) {
            return 0;
        }

        return \max(0, $this->requested - $this->actual);
    }
}
