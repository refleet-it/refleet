<?php

declare(strict_types=1);

namespace App\Notification\NotificationPreference\Infrastructure\Api\ListNotificationPreferences;

use App\Notification\NotificationPreference\Application\Query\GetNotificationPreferences\GetNotificationPreferencesQuery;
use App\Notification\NotificationPreference\Application\Query\GetNotificationPreferences\NotificationPreferenceOverview;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/notifications/preferences', name: 'notification_preference_list', methods: ['GET'])]
#[OA\Get(
    description: 'List the notification types the current account can toggle, with the channels currently enabled for each.',
    summary: 'List Notification Preferences',
    tags: ['Notification'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'List of notification preferences'),
    ]
)]
final readonly class ListNotificationPreferencesController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $handledStamp = $this->bus->dispatch(new GetNotificationPreferencesQuery(
            userId: $user->getUserId()->asString(),
        ))->last(HandledStamp::class);

        /** @var NotificationPreferenceOverview[] $preferences */
        $preferences = $handledStamp?->getResult() ?? [];

        return new JsonResponse([
            'preferences' => \array_map(static fn (NotificationPreferenceOverview $preference): array => [
                'notificationType' => $preference->notificationType,
                'label' => $preference->label,
                'enabledChannels' => $preference->enabledChannels,
            ], $preferences),
        ], Response::HTTP_OK);
    }
}
