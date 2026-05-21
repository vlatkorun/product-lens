<?php

declare(strict_types=1);

namespace App\Audit\Application\Command\Audit\Product;

use App\Audit\Domain\Orchestrator\AuditOrchestratorInterface;
use App\Audit\Domain\Pipeline\AuditPipelineResolverInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProductAuditHandler
{
    public function __construct(
        private AuditPipelineResolverInterface $pipelineResolver,
        private AuditOrchestratorInterface $orchestrator,
    ) {
    }

    public function __invoke(ProductAuditCommand $command): void
    {
        $pipelines = $this->pipelineResolver->resolve($command->auditChecks);
        $this->orchestrator->orchestrate($command->product, $pipelines);
    }
}
