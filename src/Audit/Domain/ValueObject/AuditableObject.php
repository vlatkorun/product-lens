<?php

declare(strict_types=1);

namespace App\Audit\Domain\ValueObject;

use App\Shared\Domain\ValueObject\ShopifyGid;
use Symfony\Component\Uid\UuidV7;

abstract readonly class AuditableObject
{
    public function __construct(
        private ShopifyGid $gid,
        private UuidV7 $tenantId,
    ) {
    }

    public function gid(): ShopifyGid
    {
        return $this->gid;
    }

    public function tenantId(): UuidV7
    {
        return $this->tenantId;
    }
}
