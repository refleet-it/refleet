<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Notification;

use App\Notification\NotificationPreference\Domain\Model\NotificationPreference;
use App\Shared\Domain\ValueObject\Id;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class NotificationPreferenceFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return NotificationPreference::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id' => Id::generate(),
            'userId' => Id::generate(),
            'notificationType' => self::faker()->word(),
            'enabledChannels' => [],
            'isEnabled' => true,
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('create'));
    }
}
