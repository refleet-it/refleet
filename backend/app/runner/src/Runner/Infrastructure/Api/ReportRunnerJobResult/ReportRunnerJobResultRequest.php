<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Api\ReportRunnerJobResult;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'ReportRunnerJobResult',
    description: 'Payload used by a runner to report the outcome of a previously claimed job. Reporting on a job the caller does not currently hold the lease for (already reported, or claimed by another runner) returns the existing result rather than an error, so a retried report is safe to resend.'
)]
final readonly class ReportRunnerJobResultRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[OA\Property(type: 'string', example: 'runner-fleet-01')]
        public string $runnerId,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['success', 'failure'])]
        #[OA\Property(type: 'string', enum: ['success', 'failure'], example: 'success')]
        public string $outcome,
        #[Assert\NotBlank]
        #[OA\Property(type: 'string', example: 'Project depends on acme/legacy-lib in composer.json')]
        public string $summary,
        /**
         * @var array<string, mixed>|null
         */
        #[OA\Property(description: 'Free-form structured details, reserved for future runner contract extensions.', type: 'object')]
        public ?array $details = null,
        #[OA\Property(type: 'string', example: 'git clone failed: authentication required')]
        public ?string $errorMessage = null,
        #[Assert\Range(min: 1, max: 5)]
        #[OA\Property(description: 'For kind=qualification jobs: how well the project matches the criteria, from 1 (clearly does not) to 5 (clearly does). Required on a successful qualification outcome; the Qualification context applies the cut-off.', type: 'integer', maximum: 5, minimum: 1, example: 4)]
        public ?int $score = null,
        #[Assert\Length(max: 255)]
        #[OA\Property(description: 'For kind=change jobs: the branch the runner pushed the change to.', type: 'string', example: 'refleet/change-8f0c…')]
        public ?string $branchName = null,
        #[Assert\Length(max: 500)]
        #[OA\Property(description: 'For kind=change jobs: the merge request opened (or refreshed) for the branch. Omitted when the agent changed nothing.', type: 'string', example: 'https://gitlab.com/backend-team/payments-service/-/merge_requests/42')]
        public ?string $mergeRequestUrl = null,
        #[Assert\Length(max: 64)]
        #[OA\Property(description: 'For kind=change jobs: the merge request IID within its project.', type: 'string', example: '42')]
        public ?string $mergeRequestIid = null,
    ) {
    }
}
