<?php

declare(strict_types=1);

namespace App\Tenancy\Domain\ValueObject;

enum TenantStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Uninstalled = 'uninstalled';
}
