<?php

declare(strict_types=1);

namespace App\CatalogSync\Domain\ValueObject;

final readonly class SyncCursor
{
    public function __construct(
        public ?string $endCursor,
        public bool $hasNextPage,
    ) {
    }
}
