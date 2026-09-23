<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\Register;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'Register',
    description: 'Payload used to register a new account'
)]
final readonly class RegisterRequest
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
        #[Assert\NotNull]
        #[Assert\IsTrue(message: 'You must accept the terms and conditions and privacy policy.')]
        #[OA\Property(type: 'boolean', example: true)]
        public ?bool $termsAccepted = null,
        #[OA\Property(type: 'boolean', example: false)]
        public bool $marketingConsent = false,
    ) {
    }
}
