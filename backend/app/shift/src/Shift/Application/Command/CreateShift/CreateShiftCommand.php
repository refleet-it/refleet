<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\CreateShift;

use App\Shared\Application\Command\Sync\CommandInterface;

/**
 * Three ways to resolve the target set:
 *  1. qualificationId only -> every currently QUALIFIED target of that qualification.
 *  2. qualificationId + projectIds -> those specific targets of that qualification,
 *     regardless of their status (an explicit override at Shift-creation time).
 *  3. projectIds only (no qualificationId) -> a fully manual selection of any projects
 *     in the organization, bypassing qualification entirely.
 */
final readonly class CreateShiftCommand implements CommandInterface
{
    /**
     * @param string[]|null $projectIds
     */
    public function __construct(
        public string $organizationId,
        public string $title,
        public ?string $description,
        public string $createdByAccountId,
        public ?string $qualificationId = null,
        public ?array $projectIds = null,
    ) {
    }
}
