<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Api\SendInvitation;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'SendInvitation',
    description: 'Payload used to invite someone to join the organization by email'
)]
final readonly class SendInvitationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        #[OA\Property(type: 'string', format: 'email', example: 'invitee@example.com')]
        public string $email,
    ) {
    }
}
