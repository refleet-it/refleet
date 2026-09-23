<?php

declare(strict_types=1);

namespace App\Notification\Notification\Application\DomainListener\NotificationCreated;

use App\Notification\Notification\Domain\Event\NotificationCreated;
use App\Notification\Notification\Domain\Repository\NotificationRepositoryInterface;
use App\Notification\Notification\Infrastructure\Service\EmailNotificationService;
use App\Shared\Domain\Event\DomainEventListenerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'domain_event.bus', priority: 100)]
final readonly class SendNotification implements DomainEventListenerInterface
{
    public function __construct(
        private NotificationRepositoryInterface $notificationRepository,
        private EmailNotificationService $emailNotificationService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(NotificationCreated $event): void
    {
        $notification = $this->notificationRepository->findById($event->notificationId());

        if (null === $notification) {
            $this->logger->error('Notification not found for sending', [
                'notificationId' => $event->notificationId()->asString(),
            ]);

            return;
        }

        try {
            if ($event->type()->isEmail()) {
                $success = $this->emailNotificationService->send($notification);

                if ($success) {
                    $notification->markAsSent();
                } else {
                    $notification->markAsFailed();
                }

                $this->notificationRepository->save($notification);

                $this->logger->info('Email notification processed', [
                    'notificationId' => $event->notificationId()->asString(),
                    'recipient' => $event->recipient()->value(),
                    'success' => $success,
                ]);
            } else {
                $this->logger->info('Notification type not supported yet', [
                    'notificationId' => $event->notificationId()->asString(),
                    'type' => $event->type()->value,
                ]);
            }
        } catch (\Exception $exception) {
            $notification->markAsFailed();
            $this->notificationRepository->save($notification);

            $this->logger->error('Failed to process notification', [
                'notificationId' => $event->notificationId()->asString(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
