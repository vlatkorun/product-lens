<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\DispatchCollectionSyncBatch;

final readonly class ActiveTenantBatchCriteriaDto
{
    public function __construct(
        public ?string $lastTenantId,
        public int $batchSize,
    ) {
    }
}
