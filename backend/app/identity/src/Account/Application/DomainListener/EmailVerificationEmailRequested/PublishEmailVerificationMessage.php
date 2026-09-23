<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\DomainListener\EmailVerificationEmailRequested;

use App\Identity\Account\Domain\Account\Event\EmailVerificationEmailRequested;
use App\Identity\Account\Infrastructure\Bus\EmailVerification\EmailVerificationMessage;
use App\Shared\Domain\Event\DomainEventListenerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'domain_event.bus')]
final readonly class PublishEmailVerificationMessage implements DomainEventListenerInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(EmailVerificationEmailRequested $domainEvent): void
    {
        $message = new EmailVerificationMessage(
            accountId: $domainEvent->accountId()->asString(),
            email: $domainEvent->email(),
            verificationToken: $domainEvent->verificationToken(),
        );

        $this->messageBus->dispatch($message);
    }
}
