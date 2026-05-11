<?php

declare(strict_types=1);

namespace App\CatalogSync\Domain\Model;

use App\CatalogSync\Domain\Event\SyncJobCompleted;
use App\CatalogSync\Domain\Event\SyncJobFailed;
use App\CatalogSync\Domain\Event\SyncJobProcessed;
use App\CatalogSync\Domain\Event\SyncJobStarted;
use App\CatalogSync\Domain\ValueObject\ShopifyGid;
use App\CatalogSync\Domain\ValueObject\SyncCursor;
use App\CatalogSync\Domain\ValueObject\SyncStatus;
use App\CatalogSync\Infrastructure\Persistence\DoctrineSyncJobRepository;
use App\Shared\Domain\Model\AggregateRoot;
use App\Shared\Domain\Model\TenantScopedInterface;
use App\Shared\Domain\ValueObject\FeatureFlag;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: DoctrineSyncJobRepository::class)]
#[ORM\Table(name: 'sync_jobs')]
#[ORM\Index(name: 'sync_jobs_tenant_id_idx', columns: ['tenant_id'])]
#[ORM\Index(name: 'sync_jobs_monitored_collection_id_idx', columns: ['monitored_collection_id'])]
#[ORM\Index(name: 'sync_jobs_status_idx', columns: ['status'])]
#[ORM\Index(name: 'sync_jobs_started_at_idx', columns: ['started_at'])]
#[ORM\HasLifecycleCallbacks]
class SyncJob extends AggregateRoot implements TenantScopedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private UuidV7 $id;

    #[ORM\Column(name: 'tenant_id', type: 'uuid')]
    private UuidV7 $tenantId;

    #[ORM\Column(name: 'monitored_collection_id', type: 'uuid')]
    private UuidV7 $monitoredCollectionId;

    #[ORM\Column(name: 'collection_gid', length: 255)]
    private string $collectionGidRaw;

    #[ORM\Column(type: 'sync_status', options: ['default' => 'pending'])]
    private SyncStatus $status;

    /** @var array{end_cursor: ?string, has_next_page: bool}|null */
    #[ORM\Column(name: 'cursor', type: 'jsonb', nullable: true)]
    private ?array $cursorRaw = null;

    private ?SyncCursor $cursor = null;

    #[ORM\Column(name: 'total_processed')]
    private int $totalProcessed = 0;

    #[ORM\Column(name: 'started_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(name: 'completed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(name: 'failed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $failedAt = null;

    #[ORM\Column(name: 'failure_reason', type: 'text', nullable: true)]
    private ?string $failureReason = null;

    private function __construct()
    {
    }

    public static function schedule(
        UuidV7 $tenantId,
        UuidV7 $monitoredCollectionId,
        ShopifyGid $collectionGid,
        \DateTimeImmutable $now,
    ): self {
        $job = new self();
        $job->id = new UuidV7();
        $job->tenantId = $tenantId;
        $job->monitoredCollectionId = $monitoredCollectionId;
        $job->collectionGidRaw = $collectionGid->value;
        $job->status = SyncStatus::Pending;
        $job->totalProcessed = 0;
        $job->startedAt = $now;

        return $job;
    }

    public function start(): void
    {
        $this->status = SyncStatus::Running;

        $this->raise(new SyncJobStarted(
            $this->id->toRfc4122(),
            $this->tenantId->toRfc4122(),
            $this->collectionGidRaw,
            new \DateTimeImmutable(),
        ));
    }

    /** @param list<FeatureFlag> $featureFlags */
    public function recordPage(?string $endCursor, bool $hasNextPage, int $count, array $featureFlags): void
    {
        $this->cursor = new SyncCursor($endCursor, $hasNextPage);
        $this->totalProcessed += $count;

        $now = new \DateTimeImmutable();

        $this->raise(new SyncJobProcessed(
            $this->id->toRfc4122(),
            $count,
            $this->totalProcessed,
            $now,
        ));

        if (!$hasNextPage) {
            $this->status = SyncStatus::Completed;
            $this->completedAt = $now;

            $this->raise(new SyncJobCompleted(
                $this->id->toRfc4122(),
                $this->tenantId->toRfc4122(),
                $this->collectionGidRaw,
                $featureFlags,
                $now,
            ));
        }
    }

    public function fail(string $reason, \DateTimeImmutable $at): void
    {
        $this->status = SyncStatus::Failed;
        $this->failureReason = $reason;
        $this->failedAt = $at;

        $this->raise(new SyncJobFailed(
            $this->id->toRfc4122(),
            $this->tenantId->toRfc4122(),
            $reason,
            $at,
        ));
    }

    #[ORM\PostLoad]
    public function onPostLoad(): void
    {
        $this->cursor = $this->cursorRaw !== null
            ? new SyncCursor($this->cursorRaw['end_cursor'], $this->cursorRaw['has_next_page'])
            : null;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function onPreWrite(): void
    {
        $this->cursorRaw = $this->cursor !== null
            ? ['end_cursor' => $this->cursor->endCursor, 'has_next_page' => $this->cursor->hasNextPage]
            : null;
    }

    public function id(): UuidV7
    {
        return $this->id;
    }

    public function tenantId(): UuidV7
    {
        return $this->tenantId;
    }

    public function monitoredCollectionId(): UuidV7
    {
        return $this->monitoredCollectionId;
    }

    public function collectionGid(): ShopifyGid
    {
        return ShopifyGid::fromString($this->collectionGidRaw);
    }

    public function status(): SyncStatus
    {
        return $this->status;
    }

    public function cursor(): ?SyncCursor
    {
        return $this->cursor;
    }

    public function totalProcessed(): int
    {
        return $this->totalProcessed;
    }

    public function startedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function failedAt(): ?\DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }
}
