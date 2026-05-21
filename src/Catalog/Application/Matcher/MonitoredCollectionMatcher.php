<?php

declare(strict_types=1);

namespace App\Catalog\Application\Matcher;

use App\Catalog\Domain\Model\MonitoredCollection;
use App\Catalog\Domain\Repository\MonitoredCollectionRepositoryInterface;
use App\Shared\Domain\ValueObject\ShopifyGid;
use Symfony\Component\Uid\UuidV7;

final readonly class MonitoredCollectionMatcher
{
    public function __construct(
        private MonitoredCollectionRepositoryInterface $monitoredCollectionRepository,
    ) {
    }

    /**
     * @param list<string> $collectionIds
     *
     * @return list<MonitoredCollection>
     */
    public function match(UuidV7 $tenantId, array $collectionIds): array
    {
        $matched = [];
        foreach ($collectionIds as $collectionId) {
            $collection = $this->monitoredCollectionRepository->findByTenantAndCollectionGid(
                $tenantId,
                ShopifyGid::collection($collectionId),
            );

            if ($collection !== null && $collection->isEnabled()) {
                $matched[] = $collection;
            }
        }

        return $matched;
    }
}
