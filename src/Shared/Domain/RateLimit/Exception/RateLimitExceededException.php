<?php

declare(strict_types=1);

namespace App\Shared\Domain\RateLimit\Exception;

use App\Shared\Domain\RateLimit\BucketId;

final class RateLimitExceededException extends \RuntimeException
{
    public function __construct(
        public readonly int $retryAfterSeconds,
        public readonly BucketId $bucketId,
    ) {
        parent::__construct(\sprintf('Rate limit exceeded for bucket %s, retry after %ds', $bucketId, $retryAfterSeconds));
    }
}
