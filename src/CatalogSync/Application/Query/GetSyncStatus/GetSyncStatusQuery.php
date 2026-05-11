<?php

declare(strict_types=1);

namespace App\CatalogSync\Application\Query\GetSyncStatus;

final readonly class GetSyncStatusQuery
{
    public function __construct(public string $syncJobId)
    {
    }
}
