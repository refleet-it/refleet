<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\DomainListener\PasswordResetRequested;

use App\Identity\Account\Domain\Account\Event\PasswordResetEmailRequested;
use App\Identity\Account\Domain\Account\Event\PasswordResetRequested;
use App\Shared\Domain\Event\DomainEventListenerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(priority: 100)]
final readonly class SendPasswordResetEmail implements DomainEventListenerInterface
{
    public function __construct(
        #[Autowire(service: 'domain_event.bus')]
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(PasswordResetRequested $event): void
    {
        $domainEvent = PasswordResetEmailRequested::fromPasswordResetRequested(
            $event->accountId(),
            $event->email(),
            $event->resetToken(),
            new \DateTimeImmutable()
        );

        $this->messageBus->dispatch($domainEvent);
    }
}
