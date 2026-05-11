<?php

declare(strict_types=1);

namespace App\CatalogSync\Application\Command\ProcessSyncSchedule;

use App\CatalogSync\Application\Command\StartSync\StartSyncCommand;
use App\Shared\Infrastructure\Symfony\TenantStamp;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\UuidV7;

#[AsMessageHandler]
final readonly class ProcessSyncScheduleHandler
{
    public function __construct(
        private Connection $connection,
        private MessageBusInterface $commandBus,
    ) {}

    public function __invoke(ProcessSyncScheduleCommand $command): void
    {
        $this->connection->beginTransaction();

        try {
            $rows = $this->connection->executeQuery(
                <<<'SQL'
                SELECT mc.id, mc.tenant_id, mc.collection_gid
                FROM collection_sync_configs mc
                WHERE mc.enabled = true
                  AND NOT EXISTS (
                      SELECT 1
                      FROM sync_jobs sj
                      WHERE sj.monitored_collection_id = mc.id
                        AND sj.status IN ('pending', 'running')
                  )
                FOR UPDATE SKIP LOCKED
                SQL,
            )->fetchAllAssociative();

            $now = new \DateTimeImmutable();
            $toDispatch = [];

            foreach ($rows as $row) {
                $syncJobId = (new UuidV7())->toRfc4122();

                $this->connection->executeStatement(
                    'INSERT INTO sync_jobs
                        (id, tenant_id, monitored_collection_id, collection_gid, status, total_processed, started_at)
                     VALUES (?, ?, ?, ?, \'pending\', 0, ?)',
                    [
                        $syncJobId,
                        $row['tenant_id'],
                        $row['id'],
                        $row['collection_gid'],
                        $now->format('Y-m-d H:i:s'),
                    ],
                );

                $toDispatch[] = ['syncJobId' => $syncJobId, 'tenantId' => $row['tenant_id']];
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        foreach ($toDispatch as $item) {
            $this->commandBus->dispatch(
                new StartSyncCommand($item['syncJobId']),
                [new TenantStamp(UuidV7::fromString($item['tenantId']))],
            );
        }
    }
}
