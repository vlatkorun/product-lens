<?php

declare(strict_types=1);

namespace App\Shared\Domain\Model;

use Symfony\Component\Uid\UuidV7;

interface TenantScopedInterface
{
    public function tenantId(): UuidV7;
}
