<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Domain\Qualification\Event;

use App\Qualification\Qualification\Domain\Qualification\ValueObject\AccountId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Shared\Domain\Event\DomainEvent;

final readonly class QualificationCompleted extends DomainEvent
{
    public function __construct(
        public QualificationId $qualificationId,
        public OrganizationId $organizationId,
        public AccountId $createdBy,
        public string $title,
    ) {
        parent::__construct($qualificationId);
    }
}
