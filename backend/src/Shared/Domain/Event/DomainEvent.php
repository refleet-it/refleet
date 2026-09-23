<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

use App\Shared\Domain\ValueObject\Id;

abstract readonly class DomainEvent implements DomainEventInterface
{
    public function __construct(
        protected Id $aggregateId,
    ) {
    }

    public function getAggregateId(): Id
    {
        return $this->aggregateId;
    }
}
