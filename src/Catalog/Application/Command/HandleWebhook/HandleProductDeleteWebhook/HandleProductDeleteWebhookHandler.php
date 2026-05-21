<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\HandleWebhook\HandleProductDeleteWebhook;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class HandleProductDeleteWebhookHandler
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.catalog_import')]
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(HandleProductDeleteWebhookCommand $command): void
    {
        $this->logger->info('Webhook topic is ignored, skipping', [
            'gid'       => $command->payload->gid(),
            'tenant_id' => $command->tenantId,
        ]);
    }
}
