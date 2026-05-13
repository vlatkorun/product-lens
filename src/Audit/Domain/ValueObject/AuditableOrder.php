<?php

declare(strict_types=1);

namespace App\Audit\Domain\ValueObject;

use Symfony\Component\Uid\UuidV7;

final readonly class AuditableOrder extends AuditableObject
{
    public function __construct(
        UuidV7 $orderId,
        UuidV7 $tenantId,
    ) {
        parent::__construct($orderId, $tenantId);
    }
}
