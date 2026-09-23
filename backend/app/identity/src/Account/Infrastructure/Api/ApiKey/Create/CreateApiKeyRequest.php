<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\ApiKey\Create;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(title: 'CreateApiKey')]
final readonly class CreateApiKeyRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name,
    ) {
    }
}
