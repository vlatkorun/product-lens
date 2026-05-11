<?php

declare(strict_types=1);

namespace App\Tenancy\Infrastructure\Symfony;

use App\Tenancy\Domain\Exception\InvalidOAuthCallbackException;
use App\Tenancy\Domain\Service\OAuth\OAuthStateStoreInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class OAuthStateStore implements OAuthStateStoreInterface
{
    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function store(string $state, string $shopDomain): void
    {
        $item = $this->cache->getItem($this->key($state));
        $item->set($shopDomain);
        $item->expiresAfter(600);
        $this->cache->save($item);
    }

    public function consume(string $state): string
    {
        $key = $this->key($state);
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            throw InvalidOAuthCallbackException::invalidState();
        }

        /** @var string $shopDomain */
        $shopDomain = $item->get();
        $this->cache->deleteItem($key);

        return $shopDomain;
    }

    private function key(string $state): string
    {
        return 'oauth_state_' . $state;
    }
}
