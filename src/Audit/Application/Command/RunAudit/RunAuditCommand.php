<?php

declare(strict_types=1);

namespace App\Audit\Application\Command\RunAudit;

use App\Shared\Domain\ValueObject\FeatureFlag;
use Symfony\Component\Uid\UuidV7;

final readonly class RunAuditCommand
{
    /**
     * @param list<array{url: string, altText: ?string, width: ?int, height: ?int}> $images
     * @param list<FeatureFlag> $featureFlags
     */
    public function __construct(
        public UuidV7 $productId,
        public UuidV7 $tenantId,
        public string $productTitle,
        public array $images,
        public array $featureFlags,
    ) {
    }
}
