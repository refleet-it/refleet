<?php

declare(strict_types=1);

namespace App\Notification\NotificationPreference\Infrastructure\Api\UpdateNotificationPreference;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(title: 'UpdateNotificationPreference')]
final readonly class UpdateNotificationPreferenceRequest
{
    public function __construct(
        /**
         * @var string[]
         */
        #[Assert\All([new Assert\Choice(choices: ['email', 'in_app', 'push'])])]
        #[OA\Property(description: 'Channels to enable for this notification type. Empty array disables it entirely.', type: 'array', items: new OA\Items(type: 'string', enum: ['email', 'in_app', 'push']))]
        public array $enabledChannels = [],
    ) {
    }
}
