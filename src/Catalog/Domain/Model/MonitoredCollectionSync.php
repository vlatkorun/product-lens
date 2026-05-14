<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Event\MonitoredCollectionSyncCompleted;
use App\Catalog\Domain\Event\MonitoredCollectionSyncFailed;
use App\Catalog\Domain\Event\MonitoredCollectionSyncPageSkipped;
use App\Catalog\Domain\Event\MonitoredCollectionSyncProcessed;
use App\Catalog\Domain\Event\MonitoredCollectionSyncStarted;
use App\Catalog\Domain\ValueObject\ShopifyGid;
use App\Catalog\Domain\ValueObject\SyncCursor;
use App\Catalog\Domain\ValueObject\SyncStatus;
use App\Catalog\Infrastructure\Persistence\DoctrineMonitoredCollectionSyncRepository;
use App\Shared\Domain\Model\AggregateRoot;
use App\Shared\Domain\Model\TenantScopedInterface;
use App\Shared\Domain\ValueObject\FeatureFlag;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: DoctrineMonitoredCollectionSyncRepository::class)]
#[ORM\Table(name: 'tenant_monitored_collections_sync')]
#[ORM\Index(name: 'tenant_monitored_collections_sync_tenant_id_idx', columns: ['tenant_id'])]
#[ORM\Index(name: 'tenant_monitored_collections_sync_monitored_collection_id_idx', columns: ['monitored_collection_id'])]
#[ORM\Index(name: 'tenant_monitored_collections_sync_status_idx', columns: ['status'])]
#[ORM\UniqueConstraint(name: 'tenant_monitored_collections_sync_resource_id_uq', fields: ['resourceId'])]
#[ORM\Index(name: 'tenant_monitored_collections_sync_started_at_idx', columns: ['started_at'])]
#[ORM\HasLifecycleCallbacks]
class MonitoredCollectionSync extends AggregateRoot implements TenantScopedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private ?string $id = null;

    #[ORM\Column(name: 'resource_id', type: 'uuid')]
    private UuidV7 $resourceId;

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

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

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
        $job->resourceId = new UuidV7();
        $job->tenantId = $tenantId;
        $job->monitoredCollectionId = $monitoredCollectionId;
        $job->collectionGidRaw = $collectionGid->value;
        $job->status = SyncStatus::Pending;
        $job->totalProcessed = 0;
        $job->startedAt = $now;
        $job->createdAt = $now;
        $job->updatedAt = $now;

        return $job;
    }

    public function start(): void
    {
        $this->status = SyncStatus::Running;

        $this->raise(new MonitoredCollectionSyncStarted(
            $this->resourceId->toRfc4122(),
            $this->tenantId->toRfc4122(),
            $this->collectionGidRaw,
            new \DateTimeImmutable(),
        ));
    }

    /** @param list<FeatureFlag> $featureFlags */
    public function recordPage(?string $endCursor, bool $hasNextPage, int $count, array $featureFlags): void
    {
        if ($this->status !== SyncStatus::Running) {
            $this->raise(new MonitoredCollectionSyncPageSkipped(
                $this->resourceId->toRfc4122(),
                $this->tenantId->toRfc4122(),
                $this->status,
                new \DateTimeImmutable(),
            ));

            return;
        }

        $this->cursor = new SyncCursor($endCursor, $hasNextPage);
        $this->totalProcessed += $count;

        $now = new \DateTimeImmutable();

        $this->raise(new MonitoredCollectionSyncProcessed(
            $this->resourceId->toRfc4122(),
            $count,
            $this->totalProcessed,
            $now,
        ));

        if (!$hasNextPage) {
            $this->status = SyncStatus::Completed;
            $this->completedAt = $now;

            $this->raise(new MonitoredCollectionSyncCompleted(
                $this->resourceId->toRfc4122(),
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

        $this->raise(new MonitoredCollectionSyncFailed(
            $this->resourceId->toRfc4122(),
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
    public function onPrePersist(): void
    {
        $this->cursorRaw = $this->cursor !== null
            ? ['end_cursor' => $this->cursor->endCursor, 'has_next_page' => $this->cursor->hasNextPage]
            : null;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->cursorRaw = $this->cursor !== null
            ? ['end_cursor' => $this->cursor->endCursor, 'has_next_page' => $this->cursor->hasNextPage]
            : null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function id(): UuidV7
    {
        return $this->resourceId;
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

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
