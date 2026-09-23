<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Notification;

use App\Notification\Notification\Domain\Enum\NotificationChannelEnum;
use App\Notification\Notification\Domain\Enum\NotificationPriorityEnum;
use App\Notification\Notification\Domain\Enum\NotificationStatusEnum;
use App\Notification\Notification\Domain\Enum\NotificationTypeEnum;
use App\Notification\Notification\Domain\Model\Notification;
use App\Notification\Notification\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\Id;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class NotificationFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Notification::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id' => Id::generate(),
            'recipient' => EmailAddress::fromString(self::faker()->email()),
            'subject' => self::faker()->sentence(),
            'body' => self::faker()->paragraph(),
            'type' => NotificationTypeEnum::EMAIL,
            'channel' => NotificationChannelEnum::EMAIL,
            'priority' => NotificationPriorityEnum::INFO,
            'status' => NotificationStatusEnum::PENDING,
            'isMutable' => true,
            'userId' => null,
            'createdAt' => new \DateTimeImmutable(),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('createWithStatus'));
    }
}
