<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\ResetPassword;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ResetPasswordRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Token is required')]
        public string $token,
        #[Assert\NotBlank(message: 'New password is required')]
        #[Assert\Length(min: 8, minMessage: 'Password must be at least 8 characters long')]
        public string $newPassword,
    ) {
    }
}
