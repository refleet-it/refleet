<?php

declare(strict_types=1);

namespace App\Notification\Notification\Domain\Model;

use App\Notification\Notification\Domain\Enum\NotificationChannelEnum;
use App\Notification\Notification\Domain\Enum\NotificationPriorityEnum;
use App\Notification\Notification\Domain\Enum\NotificationStatusEnum;
use App\Notification\Notification\Domain\Enum\NotificationTypeEnum;
use App\Notification\Notification\Domain\Event\NotificationCreated;
use App\Notification\Notification\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\Event\AggregateRoot;
use App\Shared\Domain\ValueObject\Id;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notifications', schema: 'notification')]
class Notification extends AggregateRoot
{
    #[ORM\Column(type: Types::STRING)]
    private string $recipient;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isRead = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::GUID, unique: true)]
        private string $id,
        EmailAddress $recipient,
        #[ORM\Column(type: Types::STRING)]
        private string $subject,
        #[ORM\Column(type: Types::TEXT)]
        private string $body,
        #[ORM\Column(type: Types::STRING, enumType: NotificationTypeEnum::class)]
        private NotificationTypeEnum $type,
        #[ORM\Column(type: Types::STRING, enumType: NotificationChannelEnum::class)]
        private NotificationChannelEnum $channel,
        #[ORM\Column(type: Types::STRING, enumType: NotificationPriorityEnum::class)]
        private NotificationPriorityEnum $priority,
        #[ORM\Column(type: Types::STRING, enumType: NotificationStatusEnum::class)]
        private NotificationStatusEnum $status,
        #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
        private bool $isMutable,
        #[ORM\Column(type: Types::GUID, nullable: true)]
        private ?string $userId,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->recipient = $recipient->value();
    }

    public static function create(
        Id $id,
        EmailAddress $recipient,
        string $subject,
        string $body,
        NotificationTypeEnum $type,
        NotificationChannelEnum $channel,
        NotificationPriorityEnum $priority = NotificationPriorityEnum::INFO,
        bool $isMutable = true,
        ?Id $userId = null,
    ): self {
        $notification = new self(
            $id->asString(),
            $recipient,
            $subject,
            $body,
            $type,
            $channel,
            $priority,
            NotificationStatusEnum::PENDING,
            $isMutable,
            $userId?->asString(),
            new \DateTimeImmutable()
        );

        $notification->recordThat(new NotificationCreated(
            $id,
            $recipient,
            $subject,
            $body,
            $type
        ));

        return $notification;
    }

    public static function createWithStatus(
        Id $id,
        EmailAddress $recipient,
        string $subject,
        string $body,
        NotificationTypeEnum $type,
        NotificationChannelEnum $channel,
        NotificationPriorityEnum $priority,
        NotificationStatusEnum $status,
        bool $isMutable,
        ?string $userId,
        \DateTimeImmutable $createdAt,
    ): self {
        $notification = new self(
            $id->asString(),
            $recipient,
            $subject,
            $body,
            $type,
            $channel,
            $priority,
            $status,
            $isMutable,
            $userId,
            $createdAt
        );

        $notification->recordThat(new NotificationCreated(
            $id,
            $recipient,
            $subject,
            $body,
            $type
        ));

        return $notification;
    }

    public function id(): Id
    {
        return Id::fromString($this->id);
    }

    public function recipient(): EmailAddress
    {
        return EmailAddress::fromString($this->recipient);
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

    public function status(): NotificationStatusEnum
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function markAsSent(): void
    {
        $this->status = NotificationStatusEnum::SENT;
    }

    public function markAsFailed(): void
    {
        $this->status = NotificationStatusEnum::FAILED;
    }

    public function channel(): NotificationChannelEnum
    {
        return $this->channel;
    }

    public function priority(): NotificationPriorityEnum
    {
        return $this->priority;
    }

    public function isMutable(): bool
    {
        return $this->isMutable;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function userId(): ?Id
    {
        return null !== $this->userId ? Id::fromString($this->userId) : null;
    }

    public function readAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function markAsRead(): void
    {
        $this->isRead = true;
        $this->readAt = new \DateTimeImmutable();
    }
}
