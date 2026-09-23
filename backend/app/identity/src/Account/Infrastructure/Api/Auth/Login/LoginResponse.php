<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\Login;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'LoginResponse',
    description: 'Response model for user login',
    properties: [
        new OA\Property(property: 'token', description: 'JWT authentication token', type: 'string'),
        new OA\Property(property: 'refreshToken', description: 'Refresh token for obtaining new JWT', type: 'string', nullable: true),
    ],
    type: 'object'
)]
final readonly class LoginResponse
{
    public function __construct(
        public string $token,
        public ?string $refreshToken,
    ) {
    }
}
