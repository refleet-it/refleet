<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Api\ReportRunnerJobResult;

use App\Runner\Runner\Application\Command\ReportRunnerJobResult\ReportedRunnerJobResult;
use App\Runner\Runner\Application\Command\ReportRunnerJobResult\ReportRunnerJobResultCommand;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/runner/jobs/{jobId}/report', name: 'runner_job_report', requirements: ['jobId' => Requirements::UUID], methods: ['POST'])]
#[OA\Post(
    description: 'Report the outcome (success or failure) of a previously claimed job. Idempotent: reporting again on a job that is no longer CLAIMED returns the existing result instead of an error.',
    summary: 'Report Runner Job Result',
    tags: ['Runner'],
    parameters: [
        new OA\Parameter(name: 'jobId', description: 'Runner Job ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Result recorded (or already-recorded result returned)'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Runner job not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'The API key owner does not belong to an organization, or the job is claimed by another runner'),
    ]
)]
final readonly class ReportRunnerJobResultController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        string $jobId,
        #[MapRequestPayload]
        ReportRunnerJobResultRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $handledStamp = $this->bus->dispatch(new ReportRunnerJobResultCommand(
            jobId: $jobId,
            runnerId: $payload->runnerId,
            organizationId: $organization->id,
            outcome: $payload->outcome,
            summary: $payload->summary,
            details: $payload->details,
            errorMessage: $payload->errorMessage,
            score: $payload->score,
            branchName: $payload->branchName,
            mergeRequestUrl: $payload->mergeRequestUrl,
            mergeRequestIid: $payload->mergeRequestIid,
        ))->last(HandledStamp::class);

        /** @var ReportedRunnerJobResult $reported */
        $reported = $handledStamp?->getResult();

        return new JsonResponse([
            'jobId' => $reported->jobId,
            'status' => $reported->status,
            'resultSummary' => $reported->resultSummary,
            'resultDetails' => $reported->resultDetails,
            'errorMessage' => $reported->errorMessage,
            'completedAt' => $reported->completedAt,
        ], Response::HTTP_OK);
    }
}
