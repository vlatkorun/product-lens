<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Tenancy;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\UuidV7;

final class TenantContext
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function activate(UuidV7 $tenantId): void
    {
        $this->entityManager->getConnection()->executeStatement(
            \sprintf("SET LOCAL app.current_tenant_id = '%s'", $tenantId->toRfc4122()),
        );

        $this->entityManager
            ->getFilters()
            ->enable('tenant')
            ->setParameter('tenant_id', $tenantId->toRfc4122(), 'string');
    }

    public function deactivate(): void
    {
        if ($this->entityManager->getFilters()->isEnabled('tenant')) {
            $this->entityManager->getFilters()->disable('tenant');
        }
    }
}
