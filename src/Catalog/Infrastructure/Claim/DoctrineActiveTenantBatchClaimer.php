<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Claim;

use App\Catalog\Application\Claim\ActiveTenantBatchClaimerInterface;
use App\Catalog\Application\Claim\ActiveTenantBatchCriteriaDto;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

final readonly class DoctrineActiveTenantBatchClaimer implements ActiveTenantBatchClaimerInterface
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return list<string> */
    public function claim(ActiveTenantBatchCriteriaDto $criteria): array
    {
        $this->connection->beginTransaction();

        try {
            $params = ['batchSize' => $criteria->batchSize];
            $types = ['batchSize' => Types::INTEGER];

            if ($criteria->lastTenantId !== null) {
                $sql = <<<'SQL'
                    SELECT resource_id
                    FROM tenants
                    WHERE status = 'active'
                      AND resource_id > :lastTenantId
                    ORDER BY resource_id ASC
                    LIMIT :batchSize
                    FOR UPDATE SKIP LOCKED
                    SQL;
                $params['lastTenantId'] = $criteria->lastTenantId;
            } else {
                $sql = <<<'SQL'
                    SELECT resource_id
                    FROM tenants
                    WHERE status = 'active'
                    ORDER BY resource_id ASC
                    LIMIT :batchSize
                    FOR UPDATE SKIP LOCKED
                    SQL;
            }

            /** @var list<string> $tenantIds */
            $tenantIds = $this->connection->executeQuery($sql, $params, $types)->fetchFirstColumn();

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return $tenantIds;
    }
}
