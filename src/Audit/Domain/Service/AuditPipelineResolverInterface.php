<?php

declare(strict_types=1);

namespace App\Audit\Domain\Service;

use App\Audit\Domain\Pipeline\AuditPipelineInterface;
use App\Shared\Domain\ValueObject\FeatureFlag;

interface AuditPipelineResolverInterface
{
    /**
     * @param list<FeatureFlag> $featureFlags
     *
     * @return list<AuditPipelineInterface>
     */
    public function resolve(array $featureFlags): array;
}
