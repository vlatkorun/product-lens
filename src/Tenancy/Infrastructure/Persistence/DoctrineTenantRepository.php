<?php

declare(strict_types=1);

namespace App\Tenancy\Infrastructure\Persistence;

use App\Tenancy\Domain\Model\Tenant;
use App\Tenancy\Domain\Repository\TenantRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DoctrineTenantRepository extends ServiceEntityRepository implements TenantRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tenant::class);
    }

    public function save(Tenant $tenant): void
    {
        $em = $this->getEntityManager();
        $em->persist($tenant);
        $em->flush();

        foreach ($tenant->pullDomainEvents() as $event) {
            // TODO: dispatch via Symfony Messenger event bus
        }
    }

    public function findByShopDomain(string $shopDomain): ?Tenant
    {
        return $this->findOneBy(['shopDomain' => $shopDomain]);
    }
}
