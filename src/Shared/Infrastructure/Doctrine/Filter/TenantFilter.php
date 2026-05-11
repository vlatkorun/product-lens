<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Filter;

use App\Shared\Domain\Model\TenantScopedInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

final class TenantFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->reflClass?->implementsInterface(TenantScopedInterface::class)) {
            return '';
        }

        return \sprintf('%s.tenant_id = %s', $targetTableAlias, $this->getParameter('tenant_id'));
    }
}
