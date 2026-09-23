<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\Login;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'Login',
    description: 'Payload used to authenticate a user',
)]
final readonly class LoginRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        #[OA\Property(type: 'string', format: 'email', example: 'user@example.com')]
        public string $email,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 128)]
        #[OA\Property(type: 'string', example: 'password123')]
        #[\SensitiveParameter]
        public string $password,
        #[OA\Property(type: 'boolean', example: false)]
        public bool $keepExistingSessions = false,
    ) {
    }
}
