<?php

declare(strict_types=1);

namespace App\Audit\Domain\ValueObject;

use App\Shared\Domain\ValueObject\ShopifyGid;
use Symfony\Component\Uid\UuidV7;

final readonly class AuditableOrder extends AuditableObject
{
    public function __construct(
        ShopifyGid $gid,
        UuidV7 $tenantId,
    ) {
        parent::__construct($gid, $tenantId);
    }
}
