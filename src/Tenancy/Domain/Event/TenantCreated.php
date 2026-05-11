<?php

declare(strict_types=1);

namespace App\Tenancy\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;

final readonly class TenantCreated implements DomainEvent
{
    public function __construct(
        public string $tenantId,
        public string $shopDomain,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
