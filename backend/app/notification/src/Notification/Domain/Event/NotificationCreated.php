<?php

declare(strict_types=1);

namespace App\Notification\Notification\Domain\Event;

use App\Notification\Notification\Domain\Enum\NotificationTypeEnum;
use App\Notification\Notification\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\ValueObject\Id;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('sync')]
final readonly class NotificationCreated implements DomainEventInterface
{
    public function __construct(
        private Id $notificationId,
        private EmailAddress $recipient,
        private string $subject,
        private string $body,
        private NotificationTypeEnum $type,
    ) {
    }

    public function notificationId(): Id
    {
        return $this->notificationId;
    }

    public function recipient(): EmailAddress
    {
        return $this->recipient;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function type(): NotificationTypeEnum
    {
        return $this->type;
    }
}
