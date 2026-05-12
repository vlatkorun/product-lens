<?php

declare(strict_types=1);

namespace App\CatalogSync\Infrastructure\Persistence;

use App\CatalogSync\Domain\Model\SyncJob;
use App\CatalogSync\Domain\Repository\SyncJobRepositoryInterface;
use App\CatalogSync\Domain\ValueObject\SyncStatus;
use App\Shared\Infrastructure\Event\DomainEventPublisher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\UuidV7;

/** @extends ServiceEntityRepository<SyncJob> */
class DoctrineSyncJobRepository extends ServiceEntityRepository implements SyncJobRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly DomainEventPublisher $eventPublisher,
    ) {
        parent::__construct($registry, SyncJob::class);
    }

    public function save(SyncJob $job): void
    {
        $em = $this->getEntityManager();
        $em->persist($job);
        $em->flush();

        foreach ($job->pullDomainEvents() as $event) {
            $this->eventPublisher->publish($event);
        }
    }

    public function findById(UuidV7 $id): ?SyncJob
    {
        return $this->findOneBy(['resourceId' => $id]);
    }

    public function findByIdForProcessing(UuidV7 $id): ?SyncJob
    {
        $em = $this->getEntityManager();

        if (!$em->getConnection()->isTransactionActive()) {
            throw new \LogicException(
                'findByIdForProcessing() must be called inside an open transaction so the row lock is held until commit.',
            );
        }

        $lockedId = $em->getConnection()->executeQuery(
            'SELECT id FROM sync_jobs WHERE resource_id = ? FOR UPDATE SKIP LOCKED',
            [$id->toRfc4122()],
        )->fetchOne();

        if ($lockedId === false) {
            return null;
        }

        return $this->find($lockedId);
    }

    /** @return list<SyncJob> */
    public function findStuckPending(\DateTimeImmutable $olderThan): array
    {
        /* @var list<SyncJob> */
        return $this->createQueryBuilder('j')
            ->where('j.status = :status')
            ->andWhere('j.startedAt < :olderThan')
            ->setParameter('status', SyncStatus::Pending->value)
            ->setParameter('olderThan', $olderThan)
            ->getQuery()
            ->getResult();
    }
}
