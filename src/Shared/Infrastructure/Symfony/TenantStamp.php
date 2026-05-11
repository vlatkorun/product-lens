<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Uid\UuidV7;

final readonly class TenantStamp implements StampInterface
{
    public function __construct(public UuidV7 $tenantId)
    {
    }
}
