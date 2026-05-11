<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case TenantAdmin = 'tenant_admin';
    case Tenant = 'tenant';

    public function isGlobal(): bool
    {
        return match ($this) {
            self::SuperAdmin, self::Admin => true,
            default                       => false,
        };
    }

    public function isTenantScoped(): bool
    {
        return !$this->isGlobal();
    }
}
