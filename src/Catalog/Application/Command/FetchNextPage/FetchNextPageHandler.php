<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\FetchNextPage;

use App\Catalog\Domain\Repository\MonitoredCollectionRepositoryInterface;
use App\Catalog\Domain\Repository\MonitoredCollectionSyncRepositoryInterface;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Domain\Service\ProductFetcherInterface;
use App\Catalog\Domain\ValueObject\ProductFilter;
use App\Shared\Infrastructure\Symfony\TenantStamp;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\UuidV7;

#[AsMessageHandler]
final readonly class FetchNextPageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MonitoredCollectionSyncRepositoryInterface $syncJobRepository,
        private MonitoredCollectionRepositoryInterface $collectionRepository,
        private ProductFetcherInterface $productFetcher,
        private ProductRepositoryInterface $productRepository,
        private MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(FetchNextPageCommand $command): void
    {
        $syncJobId = UuidV7::fromString($command->syncJobId);

        $this->entityManager->wrapInTransaction(function () use ($syncJobId, $command): void {
            $job = $this->syncJobRepository->findByIdForProcessing($syncJobId);

            if ($job === null) {
                return;
            }

            $collection = $this->collectionRepository->findById($job->monitoredCollectionId());

            if ($collection === null) {
                return;
            }

            $page = $this->productFetcher->fetchPage(
                new ProductFilter($job->collectionGid()),
                $job->tenantId(),
                $job->cursor(),
            );

            $this->productRepository->upsertAll($page->products);

            $job->recordPage(
                $page->cursor->endCursor,
                $page->cursor->hasNextPage,
                \count($page->products),
                $collection->featureFlags(),
            );

            $this->syncJobRepository->save($job);

            if ($page->cursor->hasNextPage) {
                $this->commandBus->dispatch(
                    new FetchNextPageCommand($command->syncJobId),
                    [new TenantStamp($job->tenantId())],
                );
            }
        });
    }
}
