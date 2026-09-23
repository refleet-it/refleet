<?php

declare(strict_types=1);

namespace App\Project\Project\Domain\Project\Event;

use App\Project\Project\Domain\Project\ValueObject\OrganizationId;
use App\Project\Project\Domain\Project\ValueObject\ProjectId;
use App\Shared\Domain\Event\DomainEvent;

final readonly class ProjectRegistered extends DomainEvent
{
    public function __construct(
        public ProjectId $projectId,
        public OrganizationId $organizationId,
        public string $name,
    ) {
        parent::__construct($projectId);
    }
}
