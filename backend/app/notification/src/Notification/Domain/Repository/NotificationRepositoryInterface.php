<?php

declare(strict_types=1);

namespace App\Notification\Notification\Domain\Repository;

use App\Notification\Notification\Domain\Model\Notification;
use App\Shared\Domain\ValueObject\Id;

interface NotificationRepositoryInterface
{
    public function save(Notification $notification): void;

    public function update(Notification $notification): void;

    public function findById(Id $id): ?Notification;

    /**
     * @return Notification[]
     */
    public function findPendingByType(string $type): array;
}
