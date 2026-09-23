<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\DomainListener\PasswordResetEmailRequested;

use App\Identity\Account\Domain\Account\Event\PasswordResetEmailRequested;
use App\Identity\Account\Infrastructure\Bus\PasswordReset\PasswordResetMessage;
use App\Shared\Domain\Event\DomainEventListenerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'domain_event.bus')]
final readonly class PublishPasswordResetMessage implements DomainEventListenerInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(PasswordResetEmailRequested $domainEvent): void
    {
        $message = new PasswordResetMessage(
            accountId: $domainEvent->accountId()->asString(),
            email: $domainEvent->email(),
            resetToken: $domainEvent->resetToken(),
        );

        $this->messageBus->dispatch($message);
    }
}
