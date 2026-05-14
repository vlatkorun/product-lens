<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\GetSyncStatus;

final readonly class GetSyncStatusQuery
{
    public function __construct(public string $syncJobId)
    {
    }
}
