<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Query\ListQualificationTargets;

final readonly class ListQualificationTargetsQuery
{
    /**
     * @param string[]|null $projectIds when given, restricts the result to targets for
     *                                  these projects regardless of status — used by
     *                                  Shift's "pick specific targets" creation mode
     */
    public function __construct(
        public string $qualificationId,
        public string $organizationId,
        public ?string $status = null,
        public ?array $projectIds = null,
    ) {
    }
}
