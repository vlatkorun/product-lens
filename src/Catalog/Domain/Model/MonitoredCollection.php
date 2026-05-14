<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Event\CollectionMonitoringDisabled;
use App\Catalog\Domain\Event\CollectionMonitoringEnabled;
use App\Catalog\Domain\ValueObject\MonitoredCollectionConfig;
use App\Catalog\Domain\ValueObject\ShopifyGid;
use App\Catalog\Infrastructure\Persistence\DoctrineMonitoredCollectionRepository;
use App\Shared\Domain\Model\AggregateRoot;
use App\Shared\Domain\Model\TenantScopedInterface;
use App\Shared\Domain\ValueObject\FeatureFlag;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: DoctrineMonitoredCollectionRepository::class)]
#[ORM\Table(name: 'tenant_monitored_collections')]
#[ORM\UniqueConstraint(name: 'tenant_monitored_collections_resource_id_uq', fields: ['resourceId'])]
#[ORM\UniqueConstraint(name: 'tenant_monitored_collections_tenant_collection_uq', columns: ['tenant_id', 'collection_gid'])]
#[ORM\Index(name: 'tenant_monitored_collections_tenant_id_idx', columns: ['tenant_id'])]
#[ORM\HasLifecycleCallbacks]
class MonitoredCollection extends AggregateRoot implements TenantScopedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private ?string $id = null;

    #[ORM\Column(name: 'resource_id', type: 'uuid')]
    private UuidV7 $resourceId;

    #[ORM\Column(name: 'tenant_id', type: 'uuid')]
    private UuidV7 $tenantId;

    #[ORM\Column(name: 'collection_gid', length: 255)]
    private string $collectionGidRaw;

    #[ORM\Column(name: 'collection_name', length: 255)]
    private string $collectionName;

    /** @var array{per_page: int, feature_flags: string[], priority: int} */
    #[ORM\Column(name: 'config', type: 'jsonb', options: ['default' => '{"per_page": 50, "feature_flags": [], "priority": 0}'])]
    private array $configRaw = ['per_page' => 50, 'feature_flags' => [], 'priority' => 0];

    private MonitoredCollectionConfig $config;

    #[ORM\Column]
    private bool $enabled;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function create(
        UuidV7 $tenantId,
        ShopifyGid $collectionGid,
        string $name,
        MonitoredCollectionConfig $config,
        bool $enabled,
        \DateTimeImmutable $now,
    ): self {
        $collection = new self();
        $collection->resourceId = new UuidV7();
        $collection->tenantId = $tenantId;
        $collection->collectionGidRaw = $collectionGid->value;
        $collection->collectionName = $name;
        $collection->config = $config;
        $collection->enabled = $enabled;
        $collection->createdAt = $now;
        $collection->updatedAt = $now;

        if ($enabled) {
            $collection->raise(new CollectionMonitoringEnabled(
                $collection->resourceId->toRfc4122(),
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
            $this->resourceId->toRfc4122(),
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
            $this->resourceId->toRfc4122(),
            $this->tenantId->toRfc4122(),
            $this->collectionGidRaw,
            new \DateTimeImmutable(),
        ));
    }

    public function updateConfig(MonitoredCollectionConfig $config): void
    {
        $this->config = $config;
    }

    public function rename(string $name): void
    {
        $this->collectionName = $name;
    }

    #[ORM\PostLoad]
    public function onPostLoad(): void
    {
        $this->config = new MonitoredCollectionConfig(
            perPage: $this->configRaw['per_page'],
            featureFlags: \array_values(\array_map(
                static fn (string $v): FeatureFlag => FeatureFlag::from($v),
                $this->configRaw['feature_flags'],
            )),
            priority: $this->configRaw['priority'],
        );
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->serializeConfig();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->serializeConfig();
    }

    public function id(): UuidV7
    {
        return $this->resourceId;
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

    public function config(): MonitoredCollectionConfig
    {
        return $this->config;
    }

    /** @return list<FeatureFlag> */
    public function featureFlags(): array
    {
        return $this->config->featureFlags;
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

    private function serializeConfig(): void
    {
        $this->configRaw = [
            'per_page'      => $this->config->perPage,
            'feature_flags' => \array_map(
                static fn (FeatureFlag $f): string => $f->value,
                $this->config->featureFlags,
            ),
            'priority' => $this->config->priority,
        ];
    }
}
