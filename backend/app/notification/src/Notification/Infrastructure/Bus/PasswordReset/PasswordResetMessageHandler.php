<?php

declare(strict_types=1);

namespace App\Notification\Notification\Infrastructure\Bus\PasswordReset;

use App\Notification\Notification\Domain\Enum\NotificationChannelEnum;
use App\Notification\Notification\Domain\Enum\NotificationPriorityEnum;
use App\Notification\Notification\Domain\Enum\NotificationTypeEnum;
use App\Notification\Notification\Domain\Model\Notification;
use App\Notification\Notification\Domain\Repository\NotificationRepositoryInterface;
use App\Notification\Notification\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\Id;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Twig\Environment;

#[AsMessageHandler]
final readonly class PasswordResetMessageHandler
{
    private const string RESET_PASSWORD_PATH = '/auth/reset-password?token=';

    private const string EMAIL_TEMPLATE = 'notifications/email/password_reset.html.twig';

    private const string EMAIL_SUBJECT = 'Password Reset - Refleet';

    private const string IN_APP_SUBJECT = 'Password Reset';

    private const string IN_APP_BODY = 'We received a request to reset your account password. Check your email inbox to continue.';

    public function __construct(
        private NotificationRepositoryInterface $notificationRepository,
        private Environment $twig,
        private LoggerInterface $logger,
        #[Autowire('%env(FRONTEND_URL)%')]
        private string $frontendUrl,
    ) {
    }

    public function __invoke(PasswordResetMessage $message): void
    {
        $this->logger->info('PasswordResetMessage received in Notification context', [
            'accountId' => $message->accountId,
            'email' => $message->email,
        ]);

        try {
            $userId = Id::fromString($message->accountId);
            $resetUrl = $this->frontendUrl.self::RESET_PASSWORD_PATH.\urlencode($message->resetToken);

            $emailNotification = $this->createEmailNotification($message, $userId, $resetUrl);
            $this->notificationRepository->save($emailNotification);

            $inAppNotification = $this->createInAppNotification($message, $userId);
            $this->notificationRepository->save($inAppNotification);

            $this->logger->info('Notifications created for PasswordReset message', [
                'accountId' => $message->accountId,
                'emailNotificationId' => $emailNotification->id()->asString(),
                'inAppNotificationId' => $inAppNotification->id()->asString(),
            ]);
        } catch (\Exception $exception) {
            $this->logger->error('Failed to create notifications for PasswordReset message', [
                'accountId' => $message->accountId,
                'error' => $exception->getMessage(),
            ]);

            // Re-throw to ensure message is retried
            throw $exception;
        }
    }

    private function createEmailNotification(PasswordResetMessage $message, Id $userId, string $resetUrl): Notification
    {
        $emailBody = $this->twig->render(self::EMAIL_TEMPLATE, [
            'resetUrl' => $resetUrl,
        ]);

        return Notification::create(
            id: Id::generate(),
            recipient: EmailAddress::fromString($message->email),
            subject: self::EMAIL_SUBJECT,
            body: $emailBody,
            type: NotificationTypeEnum::EMAIL,
            channel: NotificationChannelEnum::EMAIL,
            priority: NotificationPriorityEnum::INFO,
            isMutable: false,
            userId: $userId,
        );
    }

    private function createInAppNotification(PasswordResetMessage $message, Id $userId): Notification
    {
        return Notification::create(
            id: Id::generate(),
            recipient: EmailAddress::fromString($message->email),
            subject: self::IN_APP_SUBJECT,
            body: self::IN_APP_BODY,
            type: NotificationTypeEnum::PUSH,
            channel: NotificationChannelEnum::IN_APP,
            priority: NotificationPriorityEnum::INFO,
            isMutable: true,
            userId: $userId,
        );
    }
}
