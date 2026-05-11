<?php

declare(strict_types=1);

namespace App\CatalogSync\Infrastructure\Persistence;

use App\CatalogSync\Domain\Model\MonitoredCollection;
use App\CatalogSync\Domain\Repository\MonitoredCollectionRepositoryInterface;
use App\CatalogSync\Domain\ValueObject\ShopifyGid;
use App\Shared\Infrastructure\Event\DomainEventPublisher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\UuidV7;

class DoctrineMonitoredCollectionRepository extends ServiceEntityRepository implements MonitoredCollectionRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly DomainEventPublisher $eventPublisher,
    ) {
        parent::__construct($registry, MonitoredCollection::class);
    }

    public function save(MonitoredCollection $collection): void
    {
        $em = $this->getEntityManager();
        $em->persist($collection);
        $em->flush();

        foreach ($collection->pullDomainEvents() as $event) {
            $this->eventPublisher->publish($event);
        }
    }

    public function findById(UuidV7 $id): ?MonitoredCollection
    {
        return $this->find($id);
    }

    public function findByTenantAndCollectionGid(UuidV7 $tenantId, ShopifyGid $collectionGid): ?MonitoredCollection
    {
        return $this->findOneBy([
            'tenantId'         => $tenantId,
            'collectionGidRaw' => $collectionGid->value,
        ]);
    }
}
