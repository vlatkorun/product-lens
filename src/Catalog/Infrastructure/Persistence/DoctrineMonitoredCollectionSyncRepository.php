<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Model\MonitoredCollectionSync;
use App\Catalog\Domain\Repository\MonitoredCollectionSyncRepositoryInterface;
use App\Catalog\Domain\ValueObject\SyncStatus;
use App\Shared\Infrastructure\Event\DomainEventPublisher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\UuidV7;

/** @extends ServiceEntityRepository<MonitoredCollectionSync> */
class DoctrineMonitoredCollectionSyncRepository extends ServiceEntityRepository implements MonitoredCollectionSyncRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly DomainEventPublisher $eventPublisher,
    ) {
        parent::__construct($registry, MonitoredCollectionSync::class);
    }

    public function save(MonitoredCollectionSync $job): void
    {
        $em = $this->getEntityManager();
        $em->persist($job);
        $em->flush();

        foreach ($job->pullDomainEvents() as $event) {
            $this->eventPublisher->publish($event);
        }
    }

    public function findById(UuidV7 $id): ?MonitoredCollectionSync
    {
        return $this->findOneBy(['resourceId' => $id]);
    }

    public function findByIdForProcessing(UuidV7 $id): ?MonitoredCollectionSync
    {
        $em = $this->getEntityManager();

        if (!$em->getConnection()->isTransactionActive()) {
            throw new \LogicException(
                'findByIdForProcessing() must be called inside an open transaction so the row lock is held until commit.',
            );
        }

        $lockedId = $em->getConnection()->executeQuery(
            'SELECT id FROM tenant_monitored_collections_sync WHERE resource_id = ? FOR UPDATE SKIP LOCKED',
            [$id->toRfc4122()],
        )->fetchOne();

        if ($lockedId === false) {
            return null;
        }

        return $this->find($lockedId);
    }

    /** @return list<MonitoredCollectionSync> */
    public function findStuckPending(\DateTimeImmutable $olderThan): array
    {
        /* @var list<MonitoredCollectionSync> */
        return $this->createQueryBuilder('j')
            ->where('j.status = :status')
            ->andWhere('j.startedAt < :olderThan')
            ->setParameter('status', SyncStatus::Pending->value)
            ->setParameter('olderThan', $olderThan)
            ->getQuery()
            ->getResult();
    }
}
