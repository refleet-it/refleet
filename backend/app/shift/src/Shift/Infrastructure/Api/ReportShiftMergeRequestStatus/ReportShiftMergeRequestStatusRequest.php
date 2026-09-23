<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Api\ReportShiftMergeRequestStatus;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'ReportShiftMergeRequestStatus',
    description: 'Payload used to report a merge request status change for a shift target. Skeleton for a future GitLab webhook integration; today it is also called manually (e.g. from Swagger UI) to drive a target to its terminal state.'
)]
final readonly class ReportShiftMergeRequestStatusRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['opened', 'merged', 'closed'])]
        #[OA\Property(type: 'string', enum: ['opened', 'merged', 'closed'], example: 'merged')]
        public string $status,
        #[Assert\Length(max: 500)]
        #[OA\Property(type: 'string', example: 'https://gitlab.com/backend-team/payments-service/-/merge_requests/42')]
        public ?string $url = null,
        #[Assert\Length(max: 32)]
        #[OA\Property(type: 'string', example: '42')]
        public ?string $externalIid = null,
    ) {
    }
}
