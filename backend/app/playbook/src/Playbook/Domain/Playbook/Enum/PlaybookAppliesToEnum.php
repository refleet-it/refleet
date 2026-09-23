<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Domain\Playbook\Enum;

enum PlaybookAppliesToEnum: string
{
    public function covers(self $usage): bool
    {
        return self::BOTH === $this || $this === $usage;
    }

    case CHANGE = 'change';
    case QUALIFICATION = 'qualification';
    case BOTH = 'both';
}
