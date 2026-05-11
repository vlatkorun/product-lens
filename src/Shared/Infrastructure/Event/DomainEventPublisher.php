<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Event;

use App\Shared\Domain\Event\AsyncDomainEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class DomainEventPublisher
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function publish(object $event): void
    {
        if ($event instanceof AsyncDomainEvent) {
            $this->eventBus->dispatch($event);
        } else {
            $this->eventDispatcher->dispatch($event);
        }
    }
}
