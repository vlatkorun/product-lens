<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\StartSync;

use App\Catalog\Application\Command\FetchNextPage\FetchNextPageCommand;
use App\Catalog\Domain\Repository\MonitoredCollectionSyncRepositoryInterface;
use App\Shared\Infrastructure\Symfony\TenantStamp;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\UuidV7;

#[AsMessageHandler]
final readonly class StartSyncHandler
{
    public function __construct(
        private MonitoredCollectionSyncRepositoryInterface $syncJobRepository,
        private MessageBusInterface $commandBus,
        #[Autowire(service: 'monolog.logger.catalog_import')]
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(StartSyncCommand $command): void
    {
        $syncJobId = UuidV7::fromString($command->syncJobId);
        $job = $this->syncJobRepository->findById($syncJobId);

        if ($job === null) {
            $this->logger->warning('Sync job not found, skipping', ['sync_job_id' => $command->syncJobId]);

            return;
        }

        $job->start();
        $this->syncJobRepository->save($job);

        $this->logger->info('Sync job started', ['sync_job_id' => $command->syncJobId]);

        $this->commandBus->dispatch(
            new FetchNextPageCommand($command->syncJobId),
            [new TenantStamp($job->tenantId())],
        );
    }
}
