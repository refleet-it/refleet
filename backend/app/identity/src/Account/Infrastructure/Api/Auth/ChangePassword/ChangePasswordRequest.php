<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\ChangePassword;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'ChangePassword',
    description: 'Payload used to change the password of the currently authenticated account'
)]
final readonly class ChangePasswordRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Current password is required')]
        #[OA\Property(type: 'string')]
        #[\SensitiveParameter]
        public string $currentPassword,
        #[Assert\NotBlank(message: 'New password is required')]
        #[Assert\Length(min: 8, max: 128, minMessage: 'Password must be at least 8 characters long')]
        #[OA\Property(type: 'string')]
        #[\SensitiveParameter]
        public string $newPassword,
    ) {
    }
}
