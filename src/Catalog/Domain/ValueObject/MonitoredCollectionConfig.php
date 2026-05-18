<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AuditCheck;

final readonly class MonitoredCollectionConfig
{
    /** @param list<AuditCheck> $auditChecks */
    public function __construct(
        public int $perPage,
        public array $auditChecks,
        public int $priority,
    ) {
    }
}
