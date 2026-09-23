<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\DomainListener\EmailVerified;

use App\Identity\Account\Domain\Account\Event\EmailVerified;
use App\Shared\Domain\Event\DomainEventListenerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'domain_event.bus')]
final readonly class LogEmailVerified implements DomainEventListenerInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(EmailVerified $event): void
    {
        $this->logger->info('Email verified', [
            'accountId' => $event->accountId()->asString(),
            'email' => $event->email(),
        ]);
    }
}
