<?php

declare(strict_types=1);

namespace App\Audit\Domain\Service;

use App\Audit\Domain\Pipeline\AuditPipelineInterface;
use App\Shared\Domain\ValueObject\AuditCheck;

interface AuditPipelineResolverInterface
{
    /**
     * @param list<AuditCheck> $auditChecks
     *
     * @return list<AuditPipelineInterface>
     */
    public function resolve(array $auditChecks): array;
}
