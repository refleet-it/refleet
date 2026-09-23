<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Infrastructure\Api\GetPlaybook;

use App\Playbook\Playbook\Application\Query\GetPlaybook\GetPlaybookQuery;
use App\Playbook\Playbook\Application\Query\ListPlaybooks\PlaybookView;
use App\Playbook\Playbook\Infrastructure\Api\PlaybookIdRequirement;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/playbooks/{id}', name: 'playbook_get', requirements: ['id' => PlaybookIdRequirement::PATTERN], methods: ['GET'])]
#[OA\Get(
    description: "Get a single playbook, built-in or the organization's own.",
    summary: 'Get Playbook',
    tags: ['Playbook'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Playbook ID (a UUID, or "builtin:<name>" for a shipped one)', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Playbook'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Playbook not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
    ]
)]
final readonly class GetPlaybookController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        string $id,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $handledStamp = $this->bus->dispatch(new GetPlaybookQuery(
            playbookId: $id,
            organizationId: $organization->id,
        ))->last(HandledStamp::class);

        /** @var PlaybookView $view */
        $view = $handledStamp?->getResult();

        return new JsonResponse($view->toArray(), Response::HTTP_OK);
    }
}
