<?php

declare(strict_types=1);

namespace App\Shared\Domain\RateLimit;

interface ThrottleStateStoreInterface
{
    public function read(BucketId $bucketId): ?ThrottleStatus;

    public function write(BucketId $bucketId, ThrottleStatus $status): void;
}
