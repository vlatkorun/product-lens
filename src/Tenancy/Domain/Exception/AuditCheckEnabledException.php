<?php

declare(strict_types=1);

namespace App\Tenancy\Domain\Exception;

use App\Shared\Domain\ValueObject\AuditCheck;

final class AuditCheckEnabledException extends \DomainException
{
    public static function forFlag(AuditCheck $auditCheck): self
    {
        return new self(\sprintf('AuditCheck "%s" is already enabled for this tenant.', $auditCheck->value));
    }
}
