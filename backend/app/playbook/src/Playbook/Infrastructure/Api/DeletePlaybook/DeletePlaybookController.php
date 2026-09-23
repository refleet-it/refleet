<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Infrastructure\Api\DeletePlaybook;

use App\Playbook\Playbook\Application\Command\DeletePlaybook\DeletePlaybookCommand;
use App\Playbook\Playbook\Infrastructure\Api\PlaybookIdRequirement;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/playbooks/{id}', name: 'playbook_delete', requirements: ['id' => PlaybookIdRequirement::PATTERN], methods: ['DELETE'])]
#[OA\Delete(
    description: "Delete one of the organization's playbooks. Shifts and qualifications keep the text they composed from it. Built-in playbooks cannot be deleted.",
    summary: 'Delete Playbook',
    tags: ['Playbook'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Playbook ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Playbook deleted'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Playbook not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization, or the playbook is built-in'),
    ]
)]
final readonly class DeletePlaybookController
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

        $this->bus->dispatch(new DeletePlaybookCommand(
            playbookId: $id,
            organizationId: $organization->id,
        ));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
