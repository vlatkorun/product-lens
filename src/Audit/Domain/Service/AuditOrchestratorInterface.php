<?php

declare(strict_types=1);

namespace App\Audit\Domain\Service;

use App\Audit\Domain\Pipeline\AuditPipelineInterface;
use App\Audit\Domain\Pipeline\AuditPipelineResult;
use App\Audit\Domain\ValueObject\AuditableObject;

interface AuditOrchestratorInterface
{
    /**
     * @param list<AuditPipelineInterface> $pipelines
     *
     * @return list<AuditPipelineResult>
     */
    public function orchestrate(AuditableObject $subject, array $pipelines): array;
}
