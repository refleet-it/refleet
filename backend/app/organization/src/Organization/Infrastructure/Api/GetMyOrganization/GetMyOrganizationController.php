<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Api\GetMyOrganization;

use App\Organization\Organization\Application\Query\GetMyOrganization\GetMyOrganizationQuery;
use App\Organization\Organization\Application\Query\GetMyOrganization\OrganizationOverview;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/organizations/me', name: 'organization_me', methods: ['GET'])]
#[OA\Get(
    description: 'Get the organization the current account belongs to and its role in it. Returns organization: null when the account does not belong to an organization yet. Use GET /organizations/employees for the (paginated) employee list.',
    summary: 'Get My Organization',
    tags: ['Organization'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Organization overview (or null organization)'),
    ]
)]
final readonly class GetMyOrganizationController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $handledStamp = $this->bus->dispatch(new GetMyOrganizationQuery(
            accountId: $user->getUserId()->asString(),
        ))->last(HandledStamp::class);

        /** @var OrganizationOverview|null $overview */
        $overview = $handledStamp?->getResult();

        if (null === $overview) {
            return new JsonResponse(['organization' => null], Response::HTTP_OK);
        }

        return new JsonResponse([
            'organization' => [
                'id' => $overview->id,
                'name' => $overview->name,
            ],
            'role' => $overview->role,
        ], Response::HTTP_OK);
    }
}
