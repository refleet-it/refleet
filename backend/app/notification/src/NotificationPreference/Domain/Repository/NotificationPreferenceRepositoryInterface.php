<?php

declare(strict_types=1);

namespace App\Notification\NotificationPreference\Domain\Repository;

use App\Notification\NotificationPreference\Domain\Model\NotificationPreference;
use App\Shared\Domain\ValueObject\Id;

interface NotificationPreferenceRepositoryInterface
{
    public function save(NotificationPreference $preference): void;

    public function update(NotificationPreference $preference): void;

    public function findById(Id $id): ?NotificationPreference;

    public function findByUserIdAndType(Id $userId, string $notificationType): ?NotificationPreference;

    /**
     * @return NotificationPreference[]
     */
    public function findByUserId(Id $userId): array;
}
