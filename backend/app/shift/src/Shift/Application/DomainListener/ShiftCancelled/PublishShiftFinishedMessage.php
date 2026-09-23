<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\DomainListener\ShiftCancelled;

use App\Shared\Domain\Event\DomainEventListenerInterface;
use App\Shared\Domain\Service\UserEmailProviderInterface;
use App\Shared\Domain\User\UserId;
use App\Shift\Shift\Domain\Shift\Event\ShiftCancelled;
use App\Shift\Shift\Infrastructure\Bus\ShiftFinished\ShiftFinishedMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'domain_event.bus')]
final readonly class PublishShiftFinishedMessage implements DomainEventListenerInterface
{
    public function __construct(
        private UserEmailProviderInterface $userEmailProvider,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(ShiftCancelled $event): void
    {
        $email = $this->userEmailProvider->getEmailByUserId(UserId::fromString($event->createdBy->asString()));

        if (null === $email) {
            return;
        }

        $this->messageBus->dispatch(new ShiftFinishedMessage(
            accountId: $event->createdBy->asString(),
            email: $email,
            shiftId: $event->shiftId->asString(),
            title: $event->title,
            outcome: 'cancelled',
            cancelReason: $event->reason,
        ));
    }
}
