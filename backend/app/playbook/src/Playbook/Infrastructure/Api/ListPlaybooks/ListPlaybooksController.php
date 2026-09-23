<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Infrastructure\Api\ListPlaybooks;

use App\Playbook\Playbook\Application\Query\ListPlaybooks\ListPlaybooksQuery;
use App\Playbook\Playbook\Application\Query\ListPlaybooks\PlaybookView;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/playbooks', name: 'playbook_list', methods: ['GET'])]
#[OA\Get(
    description: 'List every playbook available to the organization: the built-in ones Refleet ships (read-only, ids prefixed "builtin:") followed by the organization\'s own.',
    summary: 'List Playbooks',
    tags: ['Playbook'],
    parameters: [
        new OA\Parameter(name: 'appliesTo', description: 'Only playbooks usable for this kind of prompt ("change" or "qualification"); playbooks marked "both" match either.', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['change', 'qualification'])),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Playbooks'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
    ]
)]
final readonly class ListPlaybooksController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        Request $request,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());
        $appliesTo = $request->query->getString('appliesTo');

        $handledStamp = $this->bus->dispatch(new ListPlaybooksQuery(
            organizationId: $organization->id,
            appliesTo: \in_array($appliesTo, ['change', 'qualification'], true) ? $appliesTo : null,
        ))->last(HandledStamp::class);

        /** @var list<PlaybookView> $playbooks */
        $playbooks = $handledStamp?->getResult() ?? [];

        return new JsonResponse([
            'playbooks' => \array_map(static fn (PlaybookView $view): array => $view->toArray(), $playbooks),
        ], Response::HTTP_OK);
    }
}
