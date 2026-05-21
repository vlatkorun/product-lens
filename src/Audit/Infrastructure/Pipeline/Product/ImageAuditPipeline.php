<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Pipeline\Product;

use App\Audit\Domain\Pipeline\AuditPipelineInterface;
use App\Audit\Domain\Pipeline\AuditPipelineResult;
use App\Audit\Domain\Specification\ProductSpecificationInterface;
use App\Audit\Domain\ValueObject\AuditableObject;
use App\Audit\Domain\ValueObject\AuditableProduct;
use App\Shared\Domain\ValueObject\AuditCheck;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AutoconfigureTag('app.audit_pipeline.product')]
final class ImageAuditPipeline implements AuditPipelineInterface
{
    /** @param iterable<ProductSpecificationInterface> $specifications */
    public function __construct(
        #[AutowireIterator('app.audit_specification.image')]
        private readonly iterable $specifications,
    ) {
    }

    public function name(): string
    {
        return 'image_audit';
    }

    public function requiredFeatureFlag(): AuditCheck
    {
        return AuditCheck::ImageAudit;
    }

    public function run(AuditableObject $subject): AuditPipelineResult
    {
        if (!$subject instanceof AuditableProduct) {
            throw new \InvalidArgumentException(\sprintf('%s requires %s, got %s.', self::class, AuditableProduct::class, $subject::class));
        }

        $results = [];
        foreach ($this->specifications as $specification) {
            $results[] = $specification->isSatisfiedBy($subject);
        }

        return new AuditPipelineResult($this->name(), $results);
    }
}
