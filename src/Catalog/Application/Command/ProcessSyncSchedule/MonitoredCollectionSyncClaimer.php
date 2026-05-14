<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\ProcessSyncSchedule;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\UuidV7;

final readonly class MonitoredCollectionSyncClaimer
{
    public function __construct(private Connection $connection)
    {
    }

    public function claim(\DateTimeImmutable $now): ClaimedMonitoredCollectionSyncsDto
    {
        $this->connection->beginTransaction();

        try {
            $rows = $this->connection->executeQuery(
                <<<'SQL'
                    SELECT mc.resource_id, mc.tenant_id, mc.collection_gid
                    FROM tenant_monitored_collections mc
                    WHERE mc.enabled = true
                      AND NOT EXISTS (
                          SELECT 1
                          FROM tenant_monitored_collections_sync sj
                          WHERE sj.monitored_collection_id = mc.resource_id
                            AND sj.status IN ('pending', 'running')
                      )
                    FOR UPDATE SKIP LOCKED
                    SQL,
            )->fetchAllAssociative();

            $jobs = [];

            foreach ($rows as $row) {
                $syncJobId = new UuidV7()->toRfc4122();

                $this->connection->executeStatement(
                    'INSERT INTO tenant_monitored_collections_sync
                        (resource_id, tenant_id, monitored_collection_id, collection_gid, status, total_processed, started_at, created_at, updated_at)
                     VALUES (?, ?, ?, ?, \'pending\', 0, ?, ?, ?)',
                    [
                        $syncJobId,
                        $row['tenant_id'],
                        $row['resource_id'],
                        $row['collection_gid'],
                        $now->format('Y-m-d H:i:s'),
                        $now->format('Y-m-d H:i:s'),
                        $now->format('Y-m-d H:i:s'),
                    ],
                );

                $jobs[] = new ClaimedMonitoredCollectionSyncDto($syncJobId, $row['tenant_id']);
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return new ClaimedMonitoredCollectionSyncsDto($jobs);
    }
}
