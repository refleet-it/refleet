<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Domain\Connection\ValueObject;

final readonly class GitLabOAuthTokens
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
