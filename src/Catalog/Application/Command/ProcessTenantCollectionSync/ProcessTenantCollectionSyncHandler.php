<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\ProcessTenantCollectionSync;

use App\Catalog\Domain\Repository\MonitoredCollectionRepositoryInterface;
use App\Catalog\Domain\Repository\MonitoredCollectionSyncRepositoryInterface;
use App\Catalog\Domain\Service\ProductCatalogInterface;
use App\Catalog\Domain\ValueObject\ProductFilter;
use App\Catalog\Domain\ValueObject\SyncCursor;
use App\Catalog\Domain\ValueObject\SyncStatus;
use App\Shared\Domain\RateLimit\Exception\RateLimitExceededException;
use App\Shared\Infrastructure\Symfony\TenantStamp;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
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

        // Phase 1 — no DB lock, no transaction
        $job = $this->syncJobRepository->findById($syncJobId);

        if ($job === null) {
            $this->logger->warning('Sync job not found, skipping', ['sync_job_id' => $command->syncJobId]);

            return;
        }

        if ($job->isTerminal()) {
            return;
        }

        if ($job->status() === SyncStatus::Pending) {
            $job->start();
            $this->syncJobRepository->save($job);

            $this->logger->info('Sync job started', ['sync_job_id' => $command->syncJobId]);
        }

        $collection = $this->collectionRepository->findById($job->monitoredCollectionId());

        if ($collection === null) {
            $this->logger->warning('Monitored collection not found', [
                'sync_job_id'             => $command->syncJobId,
                'monitored_collection_id' => $job->monitoredCollectionId()->toRfc4122(),
            ]);

            return;
        }

        $cursorUsed = $job->cursor();

        try {
            $result = $this->productCatalog->getPage(
                new ProductFilter($job->collectionGid()),
                $job->tenantId(),
                $cursorUsed,
                $this->productPageSize,
            );
        } catch (RateLimitExceededException $e) {
            $delayMs = \max(1000, $e->retryAfterSeconds * 1000);
            $this->commandBus->dispatch(
                new ProcessTenantCollectionSyncCommand($command->syncJobId),
                [new TenantStamp($job->tenantId()), new DelayStamp($delayMs)],
            );
            $this->logger->info('Sync paused for rate-limit window', [
                'sync_job_id'         => $command->syncJobId,
                'retry_after_seconds' => $e->retryAfterSeconds,
            ]);

            return;
        }

        // Phase 2 — tight transaction: lock, CAS, record
        $this->entityManager->wrapInTransaction(function () use ($syncJobId, $cursorUsed, $result, $collection, $command): void {
            $job = $this->syncJobRepository->findByIdForProcessing($syncJobId);

            if ($job === null) {
                $this->logger->warning('Sync job not found or locked, skipping', [
                    'sync_job_id' => $command->syncJobId,
                ]);

                return;
            }

            if (!SyncCursor::same($job->cursor(), $cursorUsed)) {
                $this->logger->info('Cursor moved during fetch, discarding stale page', [
                    'sync_job_id' => $command->syncJobId,
                ]);

                return;
            }

            $job->recordPage(
                $result->page->cursor->endCursor,
                $result->page->cursor->hasNextPage,
                \count($result->page->products),
                $collection->auditChecks(),
            );

            $this->syncJobRepository->save($job);

            $this->logger->debug('Product page fetched', [
                'sync_job_id'   => $command->syncJobId,
                'product_count' => \count($result->page->products),
                'has_next_page' => $result->page->cursor->hasNextPage,
            ]);

            if ($result->page->cursor->hasNextPage) {
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
