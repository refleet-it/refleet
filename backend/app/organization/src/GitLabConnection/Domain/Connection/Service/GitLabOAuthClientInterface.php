<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Domain\Connection\Service;

use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabOAuthTokens;

/**
 * Authorization-code flow against the one GitLab instance Refleet is registered as an
 * OAuth application on (gitlab.com). Self-hosted instances keep using pasted tokens.
 */
interface GitLabOAuthClientInterface
{
    public function isConfigured(): bool;

    public function baseUrl(): string;

    public function authorizationUrl(string $state): string;

    public function exchangeCode(string $code): GitLabOAuthTokens;

    public function refresh(string $refreshToken): GitLabOAuthTokens;
}
