<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\DispatchCollectionSyncBatch;

use App\Catalog\Application\Command\ProcessTenantsCollectionsSync\ProcessTenantsCollectionsSyncCommand;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class DispatchCollectionSyncBatchHandler
{
    public function __construct(
        private ActiveTenantBatchClaimer $claimer,
        private MessageBusInterface $commandBus,
        private int $batchSize,
    ) {
    }

    public function __invoke(DispatchCollectionSyncBatchCommand $command): void
    {
        if ($command->criteria->tenantIds !== []) {
            $this->commandBus->dispatch(new ProcessTenantsCollectionsSyncCommand($command->criteria->tenantIds));

            return;
        }

        $tenantIds = $this->claimer->claim(new ActiveTenantBatchCriteriaDto($command->criteria->lastTenantId, $this->batchSize));

        if ($tenantIds === []) {
            return;
        }

        $this->commandBus->dispatch(new ProcessTenantsCollectionsSyncCommand($tenantIds));

        if (\count($tenantIds) === $this->batchSize) {
            $this->commandBus->dispatch(new DispatchCollectionSyncBatchCommand(
                new DispatchCollectionSyncBatchCriteriaDto(lastTenantId: $tenantIds[\count($tenantIds) - 1]),
            ));
        }
    }
}
