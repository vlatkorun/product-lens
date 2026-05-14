<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\ConfigureMonitoredCollection;

final readonly class ConfigureMonitoredCollectionCommand
{
    /** @param list<string> $featureFlags FeatureFlag enum values */
    public function __construct(
        public string $tenantId,
        public string $collectionGid,
        public string $collectionName,
        public array $featureFlags,
        public int $perPage = 50,
        public int $priority = 0,
        public bool $enabled = true,
    ) {
    }
}
