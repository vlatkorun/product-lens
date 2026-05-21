<?php

declare(strict_types=1);

namespace App\Audit\Application\Command\Audit\Product;

use App\Audit\Domain\ValueObject\AuditableProduct;
use App\Shared\Domain\ValueObject\AuditCheck;

final readonly class ProductAuditCommand
{
    /** @param list<AuditCheck> $auditChecks */
    public function __construct(
        public AuditableProduct $product,
        public array $auditChecks,
    ) {
    }
}
