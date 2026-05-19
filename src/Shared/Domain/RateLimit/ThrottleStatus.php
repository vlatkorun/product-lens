<?php

declare(strict_types=1);

namespace App\Shared\Domain\RateLimit;

final readonly class ThrottleStatus
{
    public function __construct(
        public int $maximumAvailable,
        public int $currentlyAvailable,
        public int $restoreRate,
    ) {
    }
}
