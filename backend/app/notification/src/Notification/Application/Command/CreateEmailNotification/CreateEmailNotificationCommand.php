<?php

declare(strict_types=1);

namespace App\Notification\Notification\Application\Command\CreateEmailNotification;

use App\Shared\Application\Command\Sync\CommandInterface;
use App\Shared\Domain\ValueObject\Id;

final readonly class CreateEmailNotificationCommand implements CommandInterface
{
    public function __construct(
        public Id $id,
        public string $email,
        public string $subject,
        public string $body,
    ) {
    }
}
