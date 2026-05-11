<?php

declare(strict_types=1);

namespace App\Tenancy\Infrastructure\Persistence;

use App\Shared\Infrastructure\Event\DomainEventPublisher;
use App\Tenancy\Domain\Model\Tenant;
use App\Tenancy\Domain\Repository\TenantRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\UuidV7;

class DoctrineTenantRepository extends ServiceEntityRepository implements TenantRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly DomainEventPublisher $eventPublisher,
    ) {
        parent::__construct($registry, Tenant::class);
    }

    public function save(Tenant $tenant): void
    {
        $em = $this->getEntityManager();
        $em->persist($tenant);
        $em->flush();

        foreach ($tenant->pullDomainEvents() as $event) {
            $this->eventPublisher->publish($event);
        }
    }

    public function findById(UuidV7 $id): ?Tenant
    {
        return $this->find($id);
    }

    public function findByShopDomain(string $shopDomain): ?Tenant
    {
        return $this->findOneBy(['shopDomain' => $shopDomain]);
    }
}
