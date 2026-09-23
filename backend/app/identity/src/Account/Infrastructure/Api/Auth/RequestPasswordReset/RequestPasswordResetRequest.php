<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\RequestPasswordReset;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RequestPasswordResetRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required')]
        #[Assert\Email(message: 'Invalid email format')]
        public string $email,
    ) {
    }
}
