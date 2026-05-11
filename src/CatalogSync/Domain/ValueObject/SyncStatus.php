<?php

declare(strict_types=1);

namespace App\CatalogSync\Domain\ValueObject;

enum SyncStatus: string
{
    case Pending   = 'pending';
    case Running   = 'running';
    case Completed = 'completed';
    case Failed    = 'failed';
}
