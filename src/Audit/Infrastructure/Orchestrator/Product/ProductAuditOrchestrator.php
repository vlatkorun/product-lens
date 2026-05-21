<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Orchestrator\Product;

use App\Audit\Domain\Orchestrator\AuditOrchestratorInterface;
use App\Audit\Domain\Pipeline\AuditPipelineInterface;
use App\Audit\Domain\Pipeline\AuditPipelineResult;
use App\Audit\Domain\ValueObject\AuditableObject;

final class ProductAuditOrchestrator implements AuditOrchestratorInterface
{
    /**
     * @param list<AuditPipelineInterface> $pipelines
     *
     * @return list<AuditPipelineResult>
     */
    public function orchestrate(AuditableObject $subject, array $pipelines): array
    {
        $results = [];
        foreach ($pipelines as $pipeline) {
            $results[] = $pipeline->run($subject);
        }

        return $results;
    }
}
