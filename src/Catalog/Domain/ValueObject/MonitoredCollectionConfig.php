<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Shared\Domain\ValueObject\FeatureFlag;

final readonly class MonitoredCollectionConfig
{
    /** @param list<FeatureFlag> $featureFlags */
    public function __construct(
        public int $perPage,
        public array $featureFlags,
        public int $priority,
    ) {
    }
}
