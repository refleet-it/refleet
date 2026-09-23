<?php

declare(strict_types=1);

namespace App\Notification\NotificationPreference\Infrastructure\Api\UpdateNotificationPreference;

use App\Notification\NotificationPreference\Application\Command\UpdateNotificationPreference\UpdateNotificationPreferenceCommand;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/notifications/preferences/{type}', name: 'notification_preference_update', methods: ['PUT'])]
#[OA\Put(
    description: 'Set the channels enabled for a notification type on the current account.',
    summary: 'Update Notification Preference',
    tags: ['Notification'],
    responses: [
        new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Preference updated'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Unknown notification type'),
    ]
)]
final readonly class UpdateNotificationPreferenceController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        string $type,
        #[MapRequestPayload]
        UpdateNotificationPreferenceRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): Response {
        $this->bus->dispatch(new UpdateNotificationPreferenceCommand(
            userId: $user->getUserId()->asString(),
            notificationType: $type,
            enabledChannels: $payload->enabledChannels,
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
