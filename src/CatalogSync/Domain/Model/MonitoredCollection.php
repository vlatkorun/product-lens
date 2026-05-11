<?php

declare(strict_types=1);

namespace App\CatalogSync\Domain\Model;

use App\CatalogSync\Domain\Event\CollectionMonitoringDisabled;
use App\CatalogSync\Domain\Event\CollectionMonitoringEnabled;
use App\CatalogSync\Domain\ValueObject\ShopifyGid;
use App\CatalogSync\Infrastructure\Persistence\DoctrineMonitoredCollectionRepository;
use App\Shared\Domain\Model\AggregateRoot;
use App\Shared\Domain\Model\TenantScopedInterface;
use App\Shared\Domain\ValueObject\FeatureFlag;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: DoctrineMonitoredCollectionRepository::class)]
#[ORM\Table(name: 'collection_sync_configs')]
#[ORM\UniqueConstraint(name: 'collection_sync_configs_tenant_collection_uq', columns: ['tenant_id', 'collection_gid'])]
#[ORM\Index(name: 'collection_sync_configs_tenant_id_idx', columns: ['tenant_id'])]
#[ORM\HasLifecycleCallbacks]
class MonitoredCollection extends AggregateRoot implements TenantScopedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private UuidV7 $id;

    #[ORM\Column(name: 'tenant_id', type: 'uuid')]
    private UuidV7 $tenantId;

    #[ORM\Column(name: 'collection_gid', length: 255)]
    private string $collectionGidRaw;

    #[ORM\Column(name: 'collection_name', length: 255)]
    private string $collectionName;

    /** @var string[] */
    #[ORM\Column(name: 'feature_flags', type: 'jsonb', options: ['default' => '[]'])]
    private array $featureFlagsRaw = [];

    /** @var list<FeatureFlag> */
    private array $featureFlags = [];

    #[ORM\Column]
    private bool $enabled;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct() {}

    /** @param list<FeatureFlag> $featureFlags */
    public static function create(
        UuidV7 $tenantId,
        ShopifyGid $collectionGid,
        string $name,
        array $featureFlags,
        bool $enabled,
        \DateTimeImmutable $now,
    ): self {
        $collection = new self();
        $collection->id = new UuidV7();
        $collection->tenantId = $tenantId;
        $collection->collectionGidRaw = $collectionGid->value;
        $collection->collectionName = $name;
        $collection->featureFlags = $featureFlags;
        $collection->enabled = $enabled;
        $collection->createdAt = $now;
        $collection->updatedAt = $now;

        if ($enabled) {
            $collection->raise(new CollectionMonitoringEnabled(
                $collection->id->toRfc4122(),
                $tenantId->toRfc4122(),
                $collectionGid->value,
                $now,
            ));
        }

        return $collection;
    }

    public function enable(): void
    {
        if ($this->enabled) {
            return;
        }

        $this->enabled = true;
        $this->raise(new CollectionMonitoringEnabled(
            $this->id->toRfc4122(),
            $this->tenantId->toRfc4122(),
            $this->collectionGidRaw,
            new \DateTimeImmutable(),
        ));
    }

    public function disable(): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->enabled = false;
        $this->raise(new CollectionMonitoringDisabled(
            $this->id->toRfc4122(),
            $this->tenantId->toRfc4122(),
            $this->collectionGidRaw,
            new \DateTimeImmutable(),
        ));
    }

    /** @param list<FeatureFlag> $featureFlags */
    public function updateFeatureFlags(array $featureFlags): void
    {
        $this->featureFlags = $featureFlags;
    }

    public function rename(string $name): void
    {
        $this->collectionName = $name;
    }

    #[ORM\PostLoad]
    public function onPostLoad(): void
    {
        $this->featureFlags = \array_map(
            static fn(string $v): FeatureFlag => FeatureFlag::from($v),
            $this->featureFlagsRaw,
        );
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->serializeFeatureFlags();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->serializeFeatureFlags();
    }

    private function serializeFeatureFlags(): void
    {
        $this->featureFlagsRaw = \array_map(
            static fn(FeatureFlag $f): string => $f->value,
            $this->featureFlags,
        );
    }

    public function id(): UuidV7
    {
        return $this->id;
    }

    public function tenantId(): UuidV7
    {
        return $this->tenantId;
    }

    public function collectionGid(): ShopifyGid
    {
        return ShopifyGid::fromString($this->collectionGidRaw);
    }

    public function collectionName(): string
    {
        return $this->collectionName;
    }

    /** @return list<FeatureFlag> */
    public function featureFlags(): array
    {
        return $this->featureFlags;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
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
