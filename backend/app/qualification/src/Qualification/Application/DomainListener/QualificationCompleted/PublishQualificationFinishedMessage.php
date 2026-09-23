<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\DomainListener\QualificationCompleted;

use App\Qualification\Qualification\Domain\Qualification\Event\QualificationCompleted;
use App\Qualification\Qualification\Infrastructure\Bus\QualificationFinished\QualificationFinishedMessage;
use App\Shared\Domain\Event\DomainEventListenerInterface;
use App\Shared\Domain\Service\UserEmailProviderInterface;
use App\Shared\Domain\User\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'domain_event.bus')]
final readonly class PublishQualificationFinishedMessage implements DomainEventListenerInterface
{
    public function __construct(
        private UserEmailProviderInterface $userEmailProvider,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(QualificationCompleted $event): void
    {
        $email = $this->userEmailProvider->getEmailByUserId(UserId::fromString($event->createdBy->asString()));

        if (null === $email) {
            return;
        }

        $this->messageBus->dispatch(new QualificationFinishedMessage(
            accountId: $event->createdBy->asString(),
            email: $email,
            qualificationId: $event->qualificationId->asString(),
            title: $event->title,
            outcome: 'completed',
            cancelReason: null,
        ));
    }
}
