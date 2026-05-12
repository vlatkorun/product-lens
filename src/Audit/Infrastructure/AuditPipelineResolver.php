<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure;

use App\Audit\Domain\Pipeline\AuditPipelineInterface;
use App\Audit\Domain\Service\AuditPipelineResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class AuditPipelineResolver implements AuditPipelineResolverInterface
{
    /** @param iterable<AuditPipelineInterface> $pipelines */
    public function __construct(
        #[AutowireIterator('app.audit_pipeline')]
        private readonly iterable $pipelines,
    ) {
    }

    public function resolve(array $featureFlags): array
    {
        $resolved = [];
        foreach ($this->pipelines as $pipeline) {
            $required = $pipeline->requiredFeatureFlag();
            if ($required === null || \in_array($required, $featureFlags, true)) {
                $resolved[] = $pipeline;
            }
        }

        return $resolved;
    }
}
