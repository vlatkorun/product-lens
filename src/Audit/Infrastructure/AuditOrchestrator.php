<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure;

use App\Audit\Domain\Pipeline\AuditPipelineInterface;
use App\Audit\Domain\Pipeline\AuditPipelineResult;
use App\Audit\Domain\Service\AuditOrchestratorInterface;
use App\Audit\Domain\ValueObject\AuditableProduct;

final class AuditOrchestrator implements AuditOrchestratorInterface
{
    /**
     * @param list<AuditPipelineInterface> $pipelines
     *
     * @return list<AuditPipelineResult>
     */
    public function orchestrate(AuditableProduct $product, array $pipelines): array
    {
        $results = [];
        foreach ($pipelines as $pipeline) {
            $results[] = $pipeline->run($product);
        }

        return $results;
    }
}
