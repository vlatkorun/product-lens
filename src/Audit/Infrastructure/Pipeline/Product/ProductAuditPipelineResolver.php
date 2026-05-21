<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Pipeline\Product;

use App\Audit\Domain\Pipeline\AuditPipelineInterface;
use App\Audit\Domain\Pipeline\AuditPipelineResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class ProductAuditPipelineResolver implements AuditPipelineResolverInterface
{
    /** @param iterable<AuditPipelineInterface> $pipelines */
    public function __construct(
        #[AutowireIterator('app.audit_pipeline.product')]
        private readonly iterable $pipelines,
    ) {
    }

    public function resolve(array $auditChecks): array
    {
        $resolved = [];
        foreach ($this->pipelines as $pipeline) {
            $required = $pipeline->requiredFeatureFlag();
            if ($required === null || \in_array($required, $auditChecks, true)) {
                $resolved[] = $pipeline;
            }
        }

        return $resolved;
    }
}
