<?php

declare(strict_types=1);

namespace App\Audit\Domain\ValueObject;

use Symfony\Component\Uid\UuidV7;

abstract readonly class AuditableObject
{
    public function __construct(
        private UuidV7 $objectId,
        private UuidV7 $tenantId,
    ) {
    }

    public function objectId(): UuidV7
    {
        return $this->objectId;
    }

    public function tenantId(): UuidV7
    {
        return $this->tenantId;
    }
}
