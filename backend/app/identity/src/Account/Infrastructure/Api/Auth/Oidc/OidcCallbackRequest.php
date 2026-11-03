<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\Oidc;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'OIDC callback',
    description: 'The `code` and `state` the OIDC provider redirected back with',
)]
final readonly class OidcCallbackRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 4096)]
        #[OA\Property(type: 'string', example: 'a1b2c3')]
        #[\SensitiveParameter]
        public string $code,
        #[Assert\NotBlank]
        #[Assert\Length(max: 4096)]
        #[OA\Property(type: 'string', example: 'eyJuIjoi...')]
        public string $state,
        #[OA\Property(type: 'boolean', example: false)]
        public bool $marketingConsent = false,
    ) {
    }
}
