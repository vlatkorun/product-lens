<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\ConfigureMonitoredCollection;

use App\Catalog\Domain\Model\MonitoredCollection;
use App\Catalog\Domain\Repository\MonitoredCollectionRepositoryInterface;
use App\Catalog\Domain\ValueObject\MonitoredCollectionConfig;
use App\Catalog\Domain\ValueObject\ShopifyGid;
use App\Shared\Domain\ValueObject\AuditCheck;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\UuidV7;

#[AsMessageHandler]
final readonly class ConfigureMonitoredCollectionHandler
{
    public function __construct(
        private MonitoredCollectionRepositoryInterface $repository,
    ) {
    }

    public function __invoke(ConfigureMonitoredCollectionCommand $command): void
    {
        $tenantId = UuidV7::fromString($command->tenantId);
        $collectionGid = ShopifyGid::fromString($command->collectionGid);
        $config = new MonitoredCollectionConfig(
            perPage: $command->perPage,
            auditChecks: \array_map(
                static fn (string $v): AuditCheck => AuditCheck::from($v),
                $command->auditChecks,
            ),
            priority: $command->priority,
        );

        $collection = $this->repository->findByTenantAndCollectionGid($tenantId, $collectionGid);

        if ($collection === null) {
            $collection = MonitoredCollection::create(
                $tenantId,
                $collectionGid,
                $command->collectionName,
                $config,
                $command->enabled,
                new \DateTimeImmutable(),
            );
        } else {
            $collection->rename($command->collectionName);
            $collection->updateConfig($config);
            $command->enabled ? $collection->enable() : $collection->disable();
        }

        $this->repository->save($collection);
    }
}
