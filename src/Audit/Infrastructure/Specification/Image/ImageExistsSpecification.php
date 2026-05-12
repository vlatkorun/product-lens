<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Specification\Image;

use App\Audit\Domain\Specification\Issue;
use App\Audit\Domain\Specification\SpecificationInterface;
use App\Audit\Domain\Specification\SpecificationResult;
use App\Audit\Domain\Specification\ValueObject\Severity;
use App\Audit\Domain\ValueObject\AuditableProduct;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.audit_specification.image', attributes: ['priority' => 100])]
final class ImageExistsSpecification implements SpecificationInterface
{
    public function isSatisfiedBy(AuditableProduct $product): SpecificationResult
    {
        if ($product->hasImages()) {
            return SpecificationResult::pass(self::class);
        }

        return SpecificationResult::fail(
            self::class,
            Issue::of('IMAGES_MISSING', 'Product has no images.'),
            Severity::CRITICAL,
        );
    }
}
