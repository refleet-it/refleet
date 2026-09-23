<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\ReportShiftMergeRequestStatus;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class ReportShiftMergeRequestStatusCommand implements CommandInterface
{
    public function __construct(
        public string $shiftTargetId,
        public string $organizationId,
        public string $status,
        public ?string $url = null,
        public ?string $externalIid = null,
    ) {
    }
}
