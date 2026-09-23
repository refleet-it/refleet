<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\Refresh;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'Refresh',
    description: 'Payload used to refresh authentication',
)]
final readonly class RefreshRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 180)]
        #[OA\Property(type: 'string')]
        public string $refreshToken,
    ) {
    }
}
