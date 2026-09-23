<?php

declare(strict_types=1);

namespace App\Notification\NotificationPreference\Application\Command\UpdateNotificationPreference;

use App\Notification\NotificationPreference\Domain\Enum\NotificationPreferenceCatalog;
use App\Notification\NotificationPreference\Domain\Exception\UnknownNotificationTypeException;
use App\Notification\NotificationPreference\Domain\Model\NotificationPreference;
use App\Notification\NotificationPreference\Domain\Repository\NotificationPreferenceRepositoryInterface;
use App\Shared\Domain\ValueObject\Id;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateNotificationPreferenceHandler
{
    public function __construct(
        private NotificationPreferenceRepositoryInterface $preferences,
    ) {
    }

    public function __invoke(UpdateNotificationPreferenceCommand $command): void
    {
        if (!NotificationPreferenceCatalog::isKnownType($command->notificationType)) {
            throw new UnknownNotificationTypeException($command->notificationType);
        }

        $userId = Id::fromString($command->userId);
        $preference = $this->preferences->findByUserIdAndType($userId, $command->notificationType);

        if (null === $preference) {
            $preference = NotificationPreference::create(
                id: Id::generate(),
                userId: $userId,
                notificationType: $command->notificationType,
                // create() treats an empty array as "use the default channels", which would
                // silently re-enable everything if the user just turned every channel off.
                enabledChannels: $command->enabledChannels,
            );
            $preference->updateChannels($command->enabledChannels);
            $this->preferences->save($preference);

            return;
        }

        $preference->updateChannels($command->enabledChannels);
        $this->preferences->update($preference);
    }
}
