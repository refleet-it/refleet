<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Api\CreateOrganization;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'CreateOrganization',
    description: 'Payload used to create a new organization'
)]
final readonly class CreateOrganizationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 100)]
        #[OA\Property(type: 'string', example: 'Acme Inc.')]
        public string $name,
    ) {
    }
}
