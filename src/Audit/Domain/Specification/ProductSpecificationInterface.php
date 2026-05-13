<?php

declare(strict_types=1);

namespace App\Audit\Domain\Specification;

use App\Audit\Domain\ValueObject\AuditableProduct;

interface ProductSpecificationInterface
{
    public function isSatisfiedBy(AuditableProduct $product): SpecificationResult;
}
