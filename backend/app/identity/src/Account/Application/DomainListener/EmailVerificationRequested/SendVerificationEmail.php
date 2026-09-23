<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\DomainListener\EmailVerificationRequested;

use App\Identity\Account\Domain\Account\Event\EmailVerificationEmailRequested;
use App\Identity\Account\Domain\Account\Event\EmailVerificationRequested;
use App\Shared\Domain\Event\DomainEventListenerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(priority: 100)]
final readonly class SendVerificationEmail implements DomainEventListenerInterface
{
    public function __construct(
        #[Autowire(service: 'domain_event.bus')]
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(EmailVerificationRequested $event): void
    {
        $domainEvent = EmailVerificationEmailRequested::fromEmailVerificationRequested(
            $event->accountId(),
            $event->email(),
            $event->verificationToken(),
            new \DateTimeImmutable()
        );

        $this->messageBus->dispatch($domainEvent);
    }
}
