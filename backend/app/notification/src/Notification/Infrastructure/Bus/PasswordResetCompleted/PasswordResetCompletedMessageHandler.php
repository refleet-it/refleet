<?php

declare(strict_types=1);

namespace App\Notification\Notification\Infrastructure\Bus\PasswordResetCompleted;

use App\Notification\Notification\Domain\Enum\NotificationChannelEnum;
use App\Notification\Notification\Domain\Enum\NotificationPriorityEnum;
use App\Notification\Notification\Domain\Enum\NotificationTypeEnum;
use App\Notification\Notification\Domain\Model\Notification;
use App\Notification\Notification\Domain\Repository\NotificationRepositoryInterface;
use App\Notification\Notification\Domain\ValueObject\EmailAddress;
use App\Notification\NotificationPreference\Domain\Repository\NotificationPreferenceRepositoryInterface;
use App\Shared\Domain\ValueObject\Id;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Twig\Environment;

#[AsMessageHandler]
final readonly class PasswordResetCompletedMessageHandler
{
    private const string NOTIFICATION_TYPE = 'password_reset_completed';

    public function __construct(
        private NotificationRepositoryInterface $notificationRepository,
        private NotificationPreferenceRepositoryInterface $preferenceRepository,
        private Environment $twig,
        private LoggerInterface $logger,
        #[Autowire('%env(FRONTEND_URL)%')]
        private string $frontendUrl,
    ) {
    }

    public function __invoke(PasswordResetCompletedMessage $message): void
    {
        $this->logger->info('PasswordResetCompletedMessage received in Notification context', [
            'accountId' => $message->accountId,
            'email' => $message->email,
        ]);

        try {
            $userId = Id::fromString($message->accountId);
            $preference = $this->preferenceRepository->findByUserIdAndType($userId, self::NOTIFICATION_TYPE);

            $emailNotificationId = null;
            $inAppNotificationId = null;

            if (null === $preference || $preference->isChannelEnabled(NotificationChannelEnum::EMAIL)) {
                $emailNotification = $this->createEmailNotification($message, $userId);
                $this->notificationRepository->save($emailNotification);
                $emailNotificationId = $emailNotification->id()->asString();
            }

            if (null === $preference || $preference->isChannelEnabled(NotificationChannelEnum::IN_APP)) {
                $inAppNotification = $this->createInAppNotification($message, $userId);
                $this->notificationRepository->save($inAppNotification);
                $inAppNotificationId = $inAppNotification->id()->asString();
            }

            $this->logger->info('Notifications created for PasswordResetCompleted message', [
                'accountId' => $message->accountId,
                'emailNotificationId' => $emailNotificationId,
                'inAppNotificationId' => $inAppNotificationId,
            ]);
        } catch (\Exception $exception) {
            $this->logger->error('Failed to create notifications for PasswordResetCompleted message', [
                'accountId' => $message->accountId,
                'error' => $exception->getMessage(),
            ]);

            // Re-throw to ensure message is retried
            throw $exception;
        }
    }

    private function createEmailNotification(PasswordResetCompletedMessage $message, Id $userId): Notification
    {
        $emailBody = $this->twig->render('notifications/email/password_reset_confirmation.html.twig', [
            'frontendUrl' => $this->frontendUrl,
        ]);

        return Notification::create(
            id: Id::generate(),
            recipient: EmailAddress::fromString($message->email),
            subject: 'Password Changed - Refleet',
            body: $emailBody,
            type: NotificationTypeEnum::EMAIL,
            channel: NotificationChannelEnum::EMAIL,
            priority: NotificationPriorityEnum::INFO,
            isMutable: false,
            userId: $userId,
        );
    }

    private function createInAppNotification(PasswordResetCompletedMessage $message, Id $userId): Notification
    {
        return Notification::create(
            id: Id::generate(),
            recipient: EmailAddress::fromString($message->email),
            subject: 'Password Changed',
            body: "Your password has been changed successfully. If this wasn't you, contact us immediately.",
            type: NotificationTypeEnum::PUSH,
            channel: NotificationChannelEnum::IN_APP,
            priority: NotificationPriorityEnum::WARNING,
            isMutable: true,
            userId: $userId,
        );
    }
}
