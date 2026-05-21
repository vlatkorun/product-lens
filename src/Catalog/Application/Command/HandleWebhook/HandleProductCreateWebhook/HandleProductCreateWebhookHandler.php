<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\HandleWebhook\HandleProductCreateWebhook;

use App\Catalog\Domain\Model\MonitoredCollection;
use App\Catalog\Domain\Repository\MonitoredCollectionRepositoryInterface;
use App\Catalog\Domain\ValueObject\ShopifyGid;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\UuidV7;

#[AsMessageHandler]
final readonly class HandleProductCreateWebhookHandler
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.catalog_import')]
        private LoggerInterface $logger,
        private MonitoredCollectionRepositoryInterface $monitoredCollectionRepository,
    ) {
    }

    /**
     * @param list<string> $collectionIds
     *
     * @return list<MonitoredCollection>
     */
    private function resolveMonitoredCollections(UuidV7 $tenantId, array $collectionIds): array
    {
        $matched = [];
        foreach ($collectionIds as $collectionId) {
            $collection = $this->monitoredCollectionRepository->findByTenantAndCollectionGid(
                $tenantId,
                ShopifyGid::collection($collectionId),
            );

            if ($collection !== null && $collection->isEnabled()) {
                $matched[] = $collection;
            }
        }

        return $matched;
    }

    public function __invoke(HandleProductCreateWebhookCommand $command): void
    {
        $tenantId = UuidV7::fromString($command->tenantId);
        $gid = $command->payload->gid();

        $this->logger->info('Handling Shopify product create webhook', [
            'gid'       => $gid,
            'tenant_id' => $command->tenantId,
        ]);

        $monitoredCollections = $this->resolveMonitoredCollections($tenantId, $command->payload->collectionIds);

        if ($monitoredCollections === []) {
            $this->logger->info('Webhook product does not belong to any monitored collection, skipping', [
                'gid'       => $gid,
                'tenant_id' => $command->tenantId,
            ]);

            return;
        }

        // Dispatch the product audit job here
    }
}
