<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\DispatchCollectionSyncBatch;

final readonly class DispatchCollectionSyncBatchCommand
{
    public function __construct(
        public DispatchCollectionSyncBatchCriteriaDto $criteria = new DispatchCollectionSyncBatchCriteriaDto(),
    ) {
    }
}
