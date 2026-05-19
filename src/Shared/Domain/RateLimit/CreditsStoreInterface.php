<?php

declare(strict_types=1);

namespace App\Shared\Domain\RateLimit;

interface CreditsStoreInterface
{
    public function add(BucketId $bucketId, int $tokens): void;

    public function consume(BucketId $bucketId, int $upTo): int;
}
