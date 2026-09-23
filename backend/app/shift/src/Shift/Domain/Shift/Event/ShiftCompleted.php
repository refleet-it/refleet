<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\Shift\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\Shift\Shift\Domain\Shift\ValueObject\AccountId;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;

final readonly class ShiftCompleted extends DomainEvent
{
    public function __construct(
        public ShiftId $shiftId,
        public OrganizationId $organizationId,
        public AccountId $createdBy,
        public string $title,
    ) {
        parent::__construct($shiftId);
    }
}
