<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\ProcessTenantCollectionSync;

final readonly class ProcessTenantCollectionSyncCommand
{
    public function __construct(public string $syncJobId)
    {
    }
}
