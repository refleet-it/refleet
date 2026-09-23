<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

abstract class AggregateRoot
{
    /**
     * @var DomainEventInterface[]
     */
    private array $recordedDomainEvents = [];

    /**
     * @return DomainEventInterface[]
     */
    public function getRecordedDomainEvents(): array
    {
        $events = $this->recordedDomainEvents;
        $this->recordedDomainEvents = [];

        return $events;
    }

    protected function recordThat(DomainEventInterface $domainEvent): void
    {
        $this->recordedDomainEvents[] = $domainEvent;
    }
}
