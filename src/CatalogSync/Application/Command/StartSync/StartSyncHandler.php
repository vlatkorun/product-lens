<?php

declare(strict_types=1);

namespace App\CatalogSync\Application\Command\StartSync;

use App\CatalogSync\Application\Command\FetchNextPage\FetchNextPageCommand;
use App\CatalogSync\Domain\Repository\SyncJobRepositoryInterface;
use App\Shared\Infrastructure\Symfony\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\UuidV7;

#[AsMessageHandler]
final readonly class StartSyncHandler
{
    public function __construct(
        private SyncJobRepositoryInterface $syncJobRepository,
        private MessageBusInterface $commandBus,
    ) {}

    public function __invoke(StartSyncCommand $command): void
    {
        $syncJobId = UuidV7::fromString($command->syncJobId);
        $job = $this->syncJobRepository->findById($syncJobId);

        if ($job === null) {
            return;
        }

        $job->start();
        $this->syncJobRepository->save($job);

        $this->commandBus->dispatch(
            new FetchNextPageCommand($command->syncJobId),
            [new TenantStamp($job->tenantId())],
        );
    }
}
