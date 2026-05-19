<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\RateLimit\Symfony;

use App\Shared\Domain\RateLimit\BucketId;
use App\Shared\Domain\RateLimit\CreditsStoreInterface;

final readonly class RedisCreditsStore implements CreditsStoreInterface
{
    private const string KEY_PREFIX = 'credits:';

    public function __construct(private \Redis $redis)
    {
    }

    public function add(BucketId $bucketId, int $tokens): void
    {
        $this->redis->incrBy($this->key($bucketId), $tokens);
    }

    public function consume(BucketId $bucketId, int $upTo): int
    {
        $script = <<<'LUA'
            local current = tonumber(redis.call('GET', KEYS[1])) or 0
            local consume = math.min(current, tonumber(ARGV[1]))
            if consume > 0 then
                redis.call('DECRBY', KEYS[1], consume)
            end
            return consume
            LUA;

        $consumed = $this->redis->eval($script, [$this->key($bucketId), (string) $upTo], 1);

        return \is_int($consumed) ? $consumed : (int) $consumed;
    }

    private function key(BucketId $bucketId): string
    {
        return self::KEY_PREFIX . (string) $bucketId;
    }
}
