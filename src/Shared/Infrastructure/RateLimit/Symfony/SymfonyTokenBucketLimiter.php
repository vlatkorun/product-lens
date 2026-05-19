<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\RateLimit\Symfony;

use App\Shared\Domain\RateLimit\BucketId;
use App\Shared\Domain\RateLimit\Reservation;
use App\Shared\Domain\RateLimit\TokenBucketLimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class SymfonyTokenBucketLimiter implements TokenBucketLimiterInterface
{
    public function __construct(
        private RateLimiterFactory $shopifyAdminApiLimiter,
    ) {
    }

    public function tryConsume(BucketId $bucketId, int $tokens): Reservation
    {
        $limit = $this->shopifyAdminApiLimiter->create((string) $bucketId)->consume($tokens);

        if ($limit->isAccepted()) {
            return Reservation::granted($tokens);
        }

        $retryAfterSeconds = \max(0, $limit->getRetryAfter()->getTimestamp() - \time());

        return Reservation::denied($retryAfterSeconds);
    }
}
