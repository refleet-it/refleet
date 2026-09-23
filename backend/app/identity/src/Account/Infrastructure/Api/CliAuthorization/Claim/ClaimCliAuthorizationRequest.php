<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\CliAuthorization\Claim;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(title: 'ClaimCliAuthorization')]
final readonly class ClaimCliAuthorizationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 128)]
        public string $deviceSecret,
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $apiKeyName,
    ) {
    }
}
