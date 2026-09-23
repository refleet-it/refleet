<?php

declare(strict_types=1);

namespace App\Notification\Notification\Infrastructure\Bus\QualificationFinished;

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
final readonly class QualificationFinishedMessageHandler
{
    private const string NOTIFICATION_TYPE = 'qualification_finished';

    public function __construct(
        private NotificationRepositoryInterface $notificationRepository,
        private NotificationPreferenceRepositoryInterface $preferenceRepository,
        private Environment $twig,
        private LoggerInterface $logger,
        #[Autowire('%env(FRONTEND_URL)%')]
        private string $frontendUrl,
    ) {
    }

    public function __invoke(QualificationFinishedMessage $message): void
    {
        $this->logger->info('QualificationFinishedMessage received in Notification context', [
            'accountId' => $message->accountId,
            'qualificationId' => $message->qualificationId,
            'outcome' => $message->outcome,
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

            $this->logger->info('Notifications created for QualificationFinished message', [
                'accountId' => $message->accountId,
                'emailNotificationId' => $emailNotificationId,
                'inAppNotificationId' => $inAppNotificationId,
            ]);
        } catch (\Exception $exception) {
            $this->logger->error('Failed to create notifications for QualificationFinished message', [
                'accountId' => $message->accountId,
                'error' => $exception->getMessage(),
            ]);

            // Re-throw to ensure message is retried
            throw $exception;
        }
    }

    private function createEmailNotification(QualificationFinishedMessage $message, Id $userId): Notification
    {
        $emailBody = $this->twig->render('notifications/email/qualification_finished.html.twig', [
            'frontendUrl' => $this->frontendUrl,
            'qualificationId' => $message->qualificationId,
            'title' => $message->title,
            'outcome' => $message->outcome,
            'cancelReason' => $message->cancelReason,
        ]);

        $subject = 'completed' === $message->outcome
            ? \sprintf('Qualification completed: %s', $message->title)
            : \sprintf('Qualification cancelled: %s', $message->title);

        return Notification::create(
            id: Id::generate(),
            recipient: EmailAddress::fromString($message->email),
            subject: $subject,
            body: $emailBody,
            type: NotificationTypeEnum::EMAIL,
            channel: NotificationChannelEnum::EMAIL,
            priority: NotificationPriorityEnum::INFO,
            isMutable: false,
            userId: $userId,
        );
    }

    private function createInAppNotification(QualificationFinishedMessage $message, Id $userId): Notification
    {
        $body = 'completed' === $message->outcome
            ? \sprintf('Qualification "%s" has finished running.', $message->title)
            : \sprintf('Qualification "%s" was cancelled.', $message->title);

        return Notification::create(
            id: Id::generate(),
            recipient: EmailAddress::fromString($message->email),
            subject: 'completed' === $message->outcome ? 'Qualification completed' : 'Qualification cancelled',
            body: $body,
            type: NotificationTypeEnum::PUSH,
            channel: NotificationChannelEnum::IN_APP,
            priority: NotificationPriorityEnum::INFO,
            isMutable: true,
            userId: $userId,
        );
    }
}
