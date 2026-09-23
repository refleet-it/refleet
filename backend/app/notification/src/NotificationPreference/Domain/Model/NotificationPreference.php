<?php

declare(strict_types=1);

namespace App\Notification\NotificationPreference\Domain\Model;

use App\Notification\Notification\Domain\Enum\NotificationChannelEnum;
use App\Shared\Domain\Event\AggregateRoot;
use App\Shared\Domain\ValueObject\Id;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notification_preferences', schema: 'notification')]
#[ORM\UniqueConstraint(name: 'unique_user_notification_type', columns: ['user_id', 'notification_type'])]
class NotificationPreference extends AggregateRoot
{
    /**
     * @param array<string> $enabledChannels
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::GUID, unique: true)]
        private string $id,
        #[ORM\Column(type: Types::GUID)]
        private string $userId,
        #[ORM\Column(type: 'string')]
        private string $notificationType,
        #[ORM\Column(type: Types::JSON)]
        private array $enabledChannels,
        #[ORM\Column(type: 'boolean', options: ['default' => true])]
        private bool $isEnabled,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param array<string> $enabledChannels
     */
    public static function create(
        Id $id,
        Id $userId,
        string $notificationType,
        array $enabledChannels = [],
        bool $isEnabled = true,
    ): self {
        $now = new \DateTimeImmutable();

        // Default to all channels if none specified
        if ([] === $enabledChannels) {
            $enabledChannels = [
                NotificationChannelEnum::IN_APP->value,
                NotificationChannelEnum::EMAIL->value,
            ];
        }

        return new self(
            $id->asString(),
            $userId->asString(),
            $notificationType,
            $enabledChannels,
            $isEnabled,
            $now,
            $now
        );
    }

    public function id(): Id
    {
        return Id::fromString($this->id);
    }

    public function userId(): Id
    {
        return Id::fromString($this->userId);
    }

    public function notificationType(): string
    {
        return $this->notificationType;
    }

    /**
     * @return array<string>
     */
    public function enabledChannels(): array
    {
        return $this->enabledChannels;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function isChannelEnabled(NotificationChannelEnum $channel): bool
    {
        return $this->isEnabled && \in_array($channel->value, $this->enabledChannels, true);
    }

    /**
     * @param array<string> $enabledChannels
     */
    public function updateChannels(array $enabledChannels): void
    {
        $this->enabledChannels = $enabledChannels;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function enable(): void
    {
        $this->isEnabled = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function disable(): void
    {
        $this->isEnabled = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
