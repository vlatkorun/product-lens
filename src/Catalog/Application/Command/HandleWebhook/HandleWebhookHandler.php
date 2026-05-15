<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\HandleWebhook;

use App\Catalog\Domain\Model\MonitoredCollection;
use App\Catalog\Domain\Repository\MonitoredCollectionRepositoryInterface;
use App\Catalog\Domain\ValueObject\ShopifyGid;
use App\Catalog\Domain\ValueObject\ShopifyWebhookTopic;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\UuidV7;

#[AsMessageHandler]
final readonly class HandleWebhookHandler
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.catalog_import')]
        private LoggerInterface $logger,
        private MonitoredCollectionRepositoryInterface $monitoredCollectionRepository,
    ) {
    }

    /** @return list<MonitoredCollection> */
    private function resolveMonitoredCollections(HandleWebhookCommand $command, UuidV7 $tenantId): array
    {
        $collectionIds = $command->payload->collectionIds;

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

    public function __invoke(HandleWebhookCommand $command): void
    {
        $tenantId = UuidV7::fromString($command->tenantId);

        $this->logger->info('Handling Shopify webhook', [
            'topic'             => $command->topic->value,
            'shopify_object_id' => $command->shopifyObjectId,
            'tenant_id'         => $command->tenantId,
        ]);

        if ($command->topic->isFor('product')) {
            $monitoredCollections = $this->resolveMonitoredCollections($command, $tenantId);

            if ($monitoredCollections === []) {
                $this->logger->info('Webhook product does not belong to any monitored collection, skipping', [
                    'topic'             => $command->topic->value,
                    'shopify_object_id' => $command->shopifyObjectId,
                    'tenant_id'         => $command->tenantId,
                ]);

                return;
            }

            if ($command->topic === ShopifyWebhookTopic::ProductsDelete) {
                $this->logger->info('Webhook topic is ignored, skipping', [
                    'topic'             => $command->topic->value,
                    'shopify_object_id' => $command->shopifyObjectId,
                    'tenant_id'         => $command->tenantId,
                ]);

                return;
            }

            // Dispatch the product audit job here
        }
    }
}
