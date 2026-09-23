<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\DomainListener\PasswordResetConfirmationEmailRequested;

use App\Identity\Account\Domain\Account\Event\PasswordResetConfirmationEmailRequested;
use App\Identity\Account\Infrastructure\Bus\PasswordResetCompleted\PasswordResetCompletedMessage;
use App\Shared\Domain\Event\DomainEventListenerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'domain_event.bus')]
final readonly class PublishPasswordResetCompletedMessage implements DomainEventListenerInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(PasswordResetConfirmationEmailRequested $domainEvent): void
    {
        $message = new PasswordResetCompletedMessage(
            accountId: $domainEvent->accountId()->asString(),
            email: $domainEvent->email(),
        );

        $this->messageBus->dispatch($message);
    }
}
