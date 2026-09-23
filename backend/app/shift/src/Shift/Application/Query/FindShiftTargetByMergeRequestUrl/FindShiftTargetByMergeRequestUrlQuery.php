<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Query\FindShiftTargetByMergeRequestUrl;

final readonly class FindShiftTargetByMergeRequestUrlQuery
{
    public function __construct(
        public string $organizationId,
        public string $mergeRequestUrl,
    ) {
    }
}
