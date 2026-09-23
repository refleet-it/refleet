<?php

declare(strict_types=1);

namespace App\Project\Project\Infrastructure\Api\RegisterProject;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'RegisterProject',
    description: "Payload used to register or re-sync a GitLab project as part of the organization's fleet"
)]
final readonly class RegisterProjectRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[OA\Property(type: 'string', example: 'Payments Service')]
        public string $name,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d+$/', message: 'externalId must be a numeric GitLab project ID')]
        #[OA\Property(type: 'string', example: '48210942')]
        public string $externalId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[OA\Property(type: 'string', example: 'backend-team/payments-service')]
        public string $path,
        #[Assert\Length(max: 500)]
        #[OA\Property(type: 'string', example: 'https://gitlab.com/backend-team/payments-service')]
        public ?string $webUrl = null,
        #[Assert\Length(max: 100)]
        #[OA\Property(type: 'string', example: 'main')]
        public ?string $defaultBranch = null,
        #[OA\Property(type: 'string', example: 'Handles payment processing')]
        public ?string $description = null,
    ) {
    }
}
