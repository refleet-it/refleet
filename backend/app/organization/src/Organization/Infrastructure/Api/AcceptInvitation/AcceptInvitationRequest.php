<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Api\AcceptInvitation;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'AcceptInvitation',
    description: 'Payload used to accept an organization invitation and create the account'
)]
final readonly class AcceptInvitationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[OA\Property(type: 'string')]
        public string $token,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 128)]
        #[OA\Property(type: 'string', example: 'password123')]
        #[\SensitiveParameter]
        public string $password,
    ) {
    }
}
