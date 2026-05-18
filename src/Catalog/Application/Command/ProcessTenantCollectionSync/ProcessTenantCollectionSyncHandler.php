<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\ProcessTenantCollectionSync;

use App\Catalog\Domain\Repository\MonitoredCollectionRepositoryInterface;
use App\Catalog\Domain\Repository\MonitoredCollectionSyncRepositoryInterface;
use App\Catalog\Domain\Service\ProductCatalogInterface;
use App\Catalog\Domain\ValueObject\ProductFilter;
use App\Catalog\Domain\ValueObject\SyncStatus;
use App\Shared\Infrastructure\Symfony\TenantStamp;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\UuidV7;

#[AsMessageHandler]
final readonly class ProcessTenantCollectionSyncHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MonitoredCollectionSyncRepositoryInterface $syncJobRepository,
        private MonitoredCollectionRepositoryInterface $collectionRepository,
        private ProductCatalogInterface $productCatalog,
        private MessageBusInterface $commandBus,
        private int $productPageSize,
        #[Autowire(service: 'monolog.logger.catalog_import')]
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessTenantCollectionSyncCommand $command): void
    {
        $syncJobId = UuidV7::fromString($command->syncJobId);

        $job = $this->syncJobRepository->findById($syncJobId);

        if ($job === null) {
            $this->logger->warning('Sync job not found, skipping', ['sync_job_id' => $command->syncJobId]);

            return;
        }

        if ($job->status() === SyncStatus::Pending) {
            $job->start();
            $this->syncJobRepository->save($job);

            $this->logger->info('Sync job started', ['sync_job_id' => $command->syncJobId]);
        }

        $this->entityManager->wrapInTransaction(function () use ($syncJobId, $command): void {
            $job = $this->syncJobRepository->findByIdForProcessing($syncJobId);

            if ($job === null) {
                $this->logger->warning('Sync job not found or locked, skipping', [
                    'sync_job_id' => $command->syncJobId,
                ]);

                return;
            }

            $collection = $this->collectionRepository->findById($job->monitoredCollectionId());

            if ($collection === null) {
                $this->logger->warning('Monitored collection not found', [
                    'sync_job_id'             => $command->syncJobId,
                    'monitored_collection_id' => $job->monitoredCollectionId()->toRfc4122(),
                ]);

                return;
            }

            $page = $this->productCatalog->getPage(
                new ProductFilter($job->collectionGid()),
                $job->tenantId(),
                $job->cursor(),
                $this->productPageSize,
            );

            // Dispatch the product audit job here

            $job->recordPage(
                $page->cursor->endCursor,
                $page->cursor->hasNextPage,
                \count($page->products),
                $collection->auditChecks(),
            );

            $this->syncJobRepository->save($job);

            $this->logger->debug('Product page fetched', [
                'sync_job_id'   => $command->syncJobId,
                'product_count' => \count($page->products),
                'has_next_page' => $page->cursor->hasNextPage,
            ]);

            if ($page->cursor->hasNextPage) {
                $this->commandBus->dispatch(
                    new ProcessTenantCollectionSyncCommand($command->syncJobId),
                    [new TenantStamp($job->tenantId())],
                );

                return;
            }

            $this->logger->info('Collection sync completed', ['sync_job_id' => $command->syncJobId]);
        });
    }
}
