<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\HandleWebhook;

use App\Catalog\Domain\ValueObject\ShopifyGid;
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
    ) {
    }

    public function __invoke(HandleWebhookCommand $command): void
    {
        $gid = ShopifyGid::fromString($command->shopifyObjectId);
        $tenantId = UuidV7::fromString($command->tenantId);

        $this->logger->info('Handling Shopify webhook', [
            'topic' => $command->topic->value,
            'shopify_object_id' => $command->shopifyObjectId,
            'tenant_id' => $command->tenantId,
        ]);

        if($command->topic->isFor('product')) 
        {

        }
    }
}
