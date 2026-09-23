<?php

declare(strict_types=1);

namespace App\Organization\Organization\Domain\Organization\Event;

use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Shared\Domain\Event\DomainEvent;

final readonly class OrganizationCreated extends DomainEvent
{
    public function __construct(
        public OrganizationId $organizationId,
        public string $name,
    ) {
        parent::__construct($organizationId);
    }
}
