<?php

declare(strict_types=1);

namespace App\Identity\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;

final readonly class UserCreated implements DomainEvent
{
    public function __construct(
        public string $userId,
        public string $email,
        public string $role,
        public ?string $tenantId,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
