<?php

declare(strict_types=1);

namespace App\CatalogSync\Application\Command\StartSync;

final readonly class StartSyncCommand
{
    public function __construct(public string $syncJobId)
    {
    }
}
