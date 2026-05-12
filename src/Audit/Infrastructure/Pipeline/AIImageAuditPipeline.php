<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Pipeline;

use App\Audit\Domain\Pipeline\AuditPipelineInterface;
use App\Audit\Domain\Pipeline\AuditPipelineResult;
use App\Audit\Domain\Specification\SpecificationInterface;
use App\Audit\Domain\ValueObject\AuditableProduct;
use App\Shared\Domain\ValueObject\FeatureFlag;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AutoconfigureTag('app.audit_pipeline')]
final class AIImageAuditPipeline implements AuditPipelineInterface
{
    /** @param iterable<SpecificationInterface> $specifications */
    public function __construct(
        #[AutowireIterator('app.audit_specification.ai_image')]
        private readonly iterable $specifications,
    ) {
    }

    public function name(): string
    {
        return 'ai_image_audit';
    }

    public function requiredFeatureFlag(): FeatureFlag
    {
        return FeatureFlag::AiImageAudit;
    }

    public function run(AuditableProduct $product): AuditPipelineResult
    {
        $results = [];
        foreach ($this->specifications as $specification) {
            $results[] = $specification->isSatisfiedBy($product);
        }

        return new AuditPipelineResult($this->name(), $results);
    }
}
