<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Api\ListQualificationTargets;

use App\Qualification\Qualification\Application\Query\ListQualificationTargets\QualificationTargetOverview;
use App\Qualification\Qualification\Application\Query\ListQualificationTargetsPage\ListQualificationTargetsPageQuery;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\Service\RunnerDirectoryInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/qualifications/{id}/targets', name: 'qualification_target_list', requirements: ['id' => Requirements::UUID], methods: ['GET'])]
#[OA\Get(
    description: 'List the targets (projects) of a qualification, optionally filtered by status.',
    summary: 'List Qualification Targets',
    tags: ['Qualification'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Qualification ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        new OA\Parameter(name: 'status', description: 'Filter by target status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'page', description: 'Page number (default 1)', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
        new OA\Parameter(name: 'limit', description: 'Number of items per page (default 20)', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Page of qualification targets'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Qualification not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
    ]
)]
final readonly class ListQualificationTargetsController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
        private RunnerDirectoryInterface $runnerDirectory,
    ) {
    }

    public function __invoke(
        string $id,
        #[MapQueryParameter]
        ?string $status,
        #[CurrentUser]
        AccountUser $user,
        Request $request,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $page = $request->query->getInt('page');
        $limit = $request->query->getInt('limit');
        $pagination = PaginationParameters::fromRequest($page > 0 ? $page : null, $limit > 0 ? $limit : null);

        $handledStamp = $this->bus->dispatch(new ListQualificationTargetsPageQuery(
            qualificationId: $id,
            organizationId: $organization->id,
            status: $status,
            page: $pagination->getPage(),
            limit: $pagination->getLimit(),
        ))->last(HandledStamp::class);

        /** @var ListResponse<QualificationTargetOverview> $result */
        $result = $handledStamp?->getResult();

        $runnerIdsByName = $this->runnerDirectory->idsByNameForOrganization($organization->id);

        return $this->toResponse($result, $runnerIdsByName);
    }

    /**
     * @param ListResponse<QualificationTargetOverview> $result
     * @param array<string, string>                     $runnerIdsByName
     */
    private function toResponse(ListResponse $result, array $runnerIdsByName): JsonResponse
    {
        return new JsonResponse([
            'targets' => \array_map(
                static fn (QualificationTargetOverview $target): array => self::toArray($target, $runnerIdsByName),
                $result->getItems(),
            ),
            'pagination' => [
                'page' => $result->getPagination()->getPage(),
                'limit' => $result->getPagination()->getLimit(),
                'total' => $result->getTotalItems(),
                'totalPages' => $result->getTotalPages(),
                'hasNextPage' => $result->hasNextPage(),
                'hasPreviousPage' => $result->hasPreviousPage(),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * @param array<string, string> $runnerIdsByName
     *
     * @return array<string, mixed>
     */
    private static function toArray(QualificationTargetOverview $target, array $runnerIdsByName): array
    {
        return [
            'id' => $target->id,
            'qualificationId' => $target->qualificationId,
            'projectId' => $target->projectId,
            'projectSnapshot' => [
                'externalId' => $target->projectSnapshotExternalId,
                'path' => $target->projectSnapshotPath,
                'name' => $target->projectSnapshotName,
                'defaultBranch' => $target->projectSnapshotDefaultBranch,
            ],
            'status' => $target->status,
            'summary' => $target->summary,
            'score' => $target->score,
            'overridden' => $target->overridden,
            'overrideNote' => $target->overrideNote,
            'runnerName' => $target->runnerName,
            'runnerId' => null !== $target->runnerName ? $runnerIdsByName[$target->runnerName] ?? null : null,
            'createdAt' => $target->createdAt,
        ];
    }
}
