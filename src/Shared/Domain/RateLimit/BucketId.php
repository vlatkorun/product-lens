<?php

declare(strict_types=1);

namespace App\Shared\Domain\RateLimit;

use Symfony\Component\Uid\UuidV7;

final readonly class BucketId
{
    private function __construct(
        public string $namespace,
        public string $key,
    ) {
    }

    public static function shopifyAdmin(UuidV7 $tenantId): self
    {
        return new self('shopify_admin', $tenantId->toRfc4122());
    }

    public function __toString(): string
    {
        return $this->namespace . ':' . $this->key;
    }
}
