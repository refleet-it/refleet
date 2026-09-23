<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\DomainListener\PasswordResetCompleted;

use App\Identity\Account\Domain\Account\Event\PasswordResetCompleted;
use App\Identity\Account\Domain\Account\Event\PasswordResetConfirmationEmailRequested;
use App\Shared\Domain\Event\DomainEventListenerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(priority: 100)]
final readonly class SendPasswordResetConfirmationEmail implements DomainEventListenerInterface
{
    public function __construct(
        #[Autowire(service: 'domain_event.bus')]
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(PasswordResetCompleted $event): void
    {
        $domainEvent = PasswordResetConfirmationEmailRequested::fromPasswordResetCompleted(
            $event->accountId(),
            $event->email(),
            new \DateTimeImmutable()
        );

        $this->messageBus->dispatch($domainEvent);
    }
}
