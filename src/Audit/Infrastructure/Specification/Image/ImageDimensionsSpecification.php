<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Specification\Image;

use App\Audit\Domain\Specification\Issue;
use App\Audit\Domain\Specification\SpecificationInterface;
use App\Audit\Domain\Specification\SpecificationResult;
use App\Audit\Domain\Specification\ValueObject\Severity;
use App\Audit\Domain\ValueObject\AuditableProduct;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.audit_specification.image', attributes: ['priority' => 25])]
final class ImageDimensionsSpecification implements SpecificationInterface
{
    public function __construct(
        private readonly int $minWidth = 800,
        private readonly int $minHeight = 800,
    ) {
    }

    public function isSatisfiedBy(AuditableProduct $product): SpecificationResult
    {
        foreach ($product->images() as $image) {
            if ($image->width !== null && $image->width < $this->minWidth) {
                return SpecificationResult::fail(
                    self::class,
                    Issue::of(
                        'IMAGE_DIMENSIONS_TOO_SMALL',
                        \sprintf('Image width %dpx is below the required %dpx.', $image->width, $this->minWidth),
                    ),
                    Severity::WARNING,
                );
            }

            if ($image->height !== null && $image->height < $this->minHeight) {
                return SpecificationResult::fail(
                    self::class,
                    Issue::of(
                        'IMAGE_DIMENSIONS_TOO_SMALL',
                        \sprintf('Image height %dpx is below the required %dpx.', $image->height, $this->minHeight),
                    ),
                    Severity::WARNING,
                );
            }
        }

        return SpecificationResult::pass(self::class);
    }
}
