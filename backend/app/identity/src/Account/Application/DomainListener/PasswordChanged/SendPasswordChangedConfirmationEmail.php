<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\DomainListener\PasswordChanged;

use App\Identity\Account\Domain\Account\Event\PasswordChanged;
use App\Identity\Account\Domain\Account\Event\PasswordResetConfirmationEmailRequested;
use App\Shared\Domain\Event\DomainEventListenerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(priority: 100)]
final readonly class SendPasswordChangedConfirmationEmail implements DomainEventListenerInterface
{
    public function __construct(
        #[Autowire(service: 'domain_event.bus')]
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(PasswordChanged $event): void
    {
        $domainEvent = PasswordResetConfirmationEmailRequested::fromPasswordChanged(
            $event->accountId(),
            $event->email(),
            new \DateTimeImmutable()
        );

        $this->messageBus->dispatch($domainEvent);
    }
}
