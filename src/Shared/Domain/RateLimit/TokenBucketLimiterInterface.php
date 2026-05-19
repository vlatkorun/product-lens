<?php

declare(strict_types=1);

namespace App\Shared\Domain\RateLimit;

interface TokenBucketLimiterInterface
{
    public function tryConsume(BucketId $bucketId, int $tokens): Reservation;
}
