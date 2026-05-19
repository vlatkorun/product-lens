<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\RateLimit\Symfony;

use App\Shared\Domain\RateLimit\BucketId;
use App\Shared\Domain\RateLimit\ThrottleStateStoreInterface;
use App\Shared\Domain\RateLimit\ThrottleStatus;
use Psr\Cache\CacheItemPoolInterface;

final readonly class CacheThrottleStateStore implements ThrottleStateStoreInterface
{
    private const int TTL_SECONDS = 30;

    public function __construct(private CacheItemPoolInterface $rateLimiterCache)
    {
    }

    public function read(BucketId $bucketId): ?ThrottleStatus
    {
        $item = $this->rateLimiterCache->getItem($this->key($bucketId));

        if (!$item->isHit()) {
            return null;
        }

        $value = $item->get();

        return $value instanceof ThrottleStatus ? $value : null;
    }

    public function write(BucketId $bucketId, ThrottleStatus $status): void
    {
        $item = $this->rateLimiterCache->getItem($this->key($bucketId));
        $item->set($status);
        $item->expiresAfter(self::TTL_SECONDS);
        $this->rateLimiterCache->save($item);
    }

    private function key(BucketId $bucketId): string
    {
        return \str_replace(':', '_', (string) $bucketId);
    }
}
