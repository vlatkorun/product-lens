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
        return $this->find($id);
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
