<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\ProcessTenantsCollectionsSync;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\UuidV7;

final readonly class TenantScopedMonitoredCollectionSyncClaimer
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return list<ClaimedTenantCollectionSyncDto> */
    public function claim(TenantCollectionSyncClaimCriteriaDto $criteria): array
    {
        $placeholders = \implode(', ', \array_fill(0, \count($criteria->tenantIds), '?'));

        $this->connection->beginTransaction();

        try {
            if ($criteria->lastCollectionId !== null) {
                $sql = \sprintf(
                    <<<'SQL'
                        SELECT mc.resource_id, mc.tenant_id, mc.collection_gid
                        FROM tenant_monitored_collections mc
                        WHERE mc.enabled = true
                          AND mc.tenant_id IN (%s)
                          AND mc.resource_id > ?
                          AND NOT EXISTS (
                              SELECT 1
                              FROM tenant_monitored_collections_sync sj
                              WHERE sj.monitored_collection_id = mc.resource_id
                                AND sj.status IN ('pending', 'running')
                          )
                        ORDER BY mc.resource_id ASC
                        LIMIT %d
                        FOR UPDATE SKIP LOCKED
                        SQL,
                    $placeholders,
                    $criteria->batchSize,
                );
                $params = [...$criteria->tenantIds, $criteria->lastCollectionId];
            } else {
                $sql = \sprintf(
                    <<<'SQL'
                        SELECT mc.resource_id, mc.tenant_id, mc.collection_gid
                        FROM tenant_monitored_collections mc
                        WHERE mc.enabled = true
                          AND mc.tenant_id IN (%s)
                          AND NOT EXISTS (
                              SELECT 1
                              FROM tenant_monitored_collections_sync sj
                              WHERE sj.monitored_collection_id = mc.resource_id
                                AND sj.status IN ('pending', 'running')
                          )
                        ORDER BY mc.resource_id ASC
                        LIMIT %d
                        FOR UPDATE SKIP LOCKED
                        SQL,
                    $placeholders,
                    $criteria->batchSize,
                );
                $params = $criteria->tenantIds;
            }

            $rows = $this->connection->executeQuery($sql, $params)->fetchAllAssociative();

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
                        $criteria->now->format('Y-m-d H:i:s'),
                        $criteria->now->format('Y-m-d H:i:s'),
                        $criteria->now->format('Y-m-d H:i:s'),
                    ],
                );

                $jobs[] = new ClaimedTenantCollectionSyncDto($syncJobId, $row['tenant_id'], $row['resource_id']);
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return $jobs;
    }
}
