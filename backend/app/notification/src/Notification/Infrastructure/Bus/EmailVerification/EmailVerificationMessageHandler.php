<?php

declare(strict_types=1);

namespace App\Notification\Notification\Infrastructure\Bus\EmailVerification;

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
final readonly class EmailVerificationMessageHandler
{
    public function __construct(
        private NotificationRepositoryInterface $notificationRepository,
        private Environment $twig,
        private LoggerInterface $logger,
        #[Autowire('%env(FRONTEND_URL)%')]
        private string $frontendUrl,
    ) {
    }

    public function __invoke(EmailVerificationMessage $message): void
    {
        $this->logger->info('EmailVerificationMessage received in Notification context', [
            'accountId' => $message->accountId,
            'email' => $message->email,
        ]);

        try {
            $verificationUrl = $this->frontendUrl.'/auth/verify-email?token='.$message->verificationToken;

            $emailBody = $this->twig->render('notifications/email/email_verification.html.twig', [
                'verificationUrl' => $verificationUrl,
            ]);

            $emailNotification = Notification::create(
                id: Id::generate(),
                recipient: EmailAddress::fromString($message->email),
                subject: 'Confirm your email address - Refleet',
                body: $emailBody,
                type: NotificationTypeEnum::EMAIL,
                channel: NotificationChannelEnum::EMAIL,
                priority: NotificationPriorityEnum::INFO,
                isMutable: false,
                userId: Id::fromString($message->accountId),
            );

            $this->notificationRepository->save($emailNotification);

            $this->logger->info('Email verification notification created', [
                'accountId' => $message->accountId,
                'emailNotificationId' => $emailNotification->id()->asString(),
            ]);
        } catch (\Exception $exception) {
            $this->logger->error('Failed to create notification for EmailVerification message', [
                'accountId' => $message->accountId,
                'error' => $exception->getMessage(),
            ]);

            // Re-throw to ensure message is retried
            throw $exception;
        }
    }
}
