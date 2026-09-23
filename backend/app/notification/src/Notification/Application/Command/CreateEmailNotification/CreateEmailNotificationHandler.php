<?php

declare(strict_types=1);

namespace App\Notification\Notification\Application\Command\CreateEmailNotification;

use App\Notification\Notification\Domain\Enum\NotificationChannelEnum;
use App\Notification\Notification\Domain\Enum\NotificationTypeEnum;
use App\Notification\Notification\Domain\Model\Notification;
use App\Notification\Notification\Domain\Repository\NotificationRepositoryInterface;
use App\Notification\Notification\Domain\ValueObject\EmailAddress;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateEmailNotificationHandler
{
    public function __construct(
        private NotificationRepositoryInterface $notificationRepository,
    ) {
    }

    public function __invoke(CreateEmailNotificationCommand $command): void
    {
        $notification = Notification::create(
            $command->id,
            EmailAddress::fromString($command->email),
            $command->subject,
            $command->body,
            NotificationTypeEnum::EMAIL,
            NotificationChannelEnum::EMAIL
        );

        $this->notificationRepository->save($notification);
    }
}
