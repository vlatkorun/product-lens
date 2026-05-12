<?php

declare(strict_types=1);

namespace App\Audit\Application\Command\RunAudit;

use App\Audit\Domain\Service\AuditOrchestratorInterface;
use App\Audit\Domain\Service\AuditPipelineResolverInterface;
use App\Audit\Domain\ValueObject\AuditableProduct;
use App\Audit\Domain\ValueObject\ProductImage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RunAuditHandler
{
    public function __construct(
        private AuditPipelineResolverInterface $pipelineResolver,
        private AuditOrchestratorInterface $orchestrator,
    ) {
    }

    public function __invoke(RunAuditCommand $command): void
    {
        $product = new AuditableProduct(
            productId: $command->productId,
            tenantId: $command->tenantId,
            title: $command->productTitle,
            images: \array_map(
                static fn (array $img) => new ProductImage(
                    url: $img['url'],
                    altText: $img['altText'],
                    width: $img['width'],
                    height: $img['height'],
                ),
                $command->images,
            ),
        );

        $pipelines = $this->pipelineResolver->resolve($command->featureFlags);
        $this->orchestrator->orchestrate($product, $pipelines);
    }
}
