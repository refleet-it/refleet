<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Domain\Connection\Service;

use App\Organization\GitLabConnection\Domain\Connection\Model\GitLabConnection;
use App\Organization\GitLabConnection\Domain\Connection\Repository\GitLabConnectionRepositoryInterface;

/**
 * The one place a usable GitLab access token comes from. Pasted tokens are just
 * decrypted; OAuth tokens are refreshed first when they are about to expire, under a row
 * lock, because GitLab invalidates a refresh token the moment it is used — two workers
 * refreshing the same connection at once would otherwise leave one of them with a token
 * GitLab no longer accepts and the database with whichever pair was written last.
 */
final readonly class GitLabAccessTokenResolver
{
    /**
     * Comfortably longer than a clone or push takes, so a token handed out here is still
     * valid when the caller actually uses it.
     */
    public const int REFRESH_LEEWAY_SECONDS = 600;

    public function __construct(
        private GitLabConnectionRepositoryInterface $connections,
        private GitLabTokenEncryptorInterface $tokenEncryptor,
        private GitLabOAuthClientInterface $oauthClient,
    ) {
    }

    public function resolve(GitLabConnection $connection): string
    {
        if (!$connection->usesOAuth() || !$connection->accessTokenExpiresWithin(self::REFRESH_LEEWAY_SECONDS, new \DateTimeImmutable())) {
            return $this->tokenEncryptor->decrypt($connection->accessTokenCiphertext());
        }

        return $this->connections->withExclusiveLock($connection->organizationId(), function (GitLabConnection $locked): string {
            // Another worker may have refreshed while we waited for the lock.
            if (!$locked->accessTokenExpiresWithin(self::REFRESH_LEEWAY_SECONDS, new \DateTimeImmutable())) {
                return $this->tokenEncryptor->decrypt($locked->accessTokenCiphertext());
            }

            $refreshToken = $locked->refreshTokenCiphertext();
            \assert(null !== $refreshToken);

            $tokens = $this->oauthClient->refresh($this->tokenEncryptor->decrypt($refreshToken));

            $locked->rotateOAuthTokens(
                accessTokenCiphertext: $this->tokenEncryptor->encrypt($tokens->accessToken),
                refreshTokenCiphertext: $this->tokenEncryptor->encrypt($tokens->refreshToken),
                accessTokenExpiresAt: $tokens->expiresAt,
            );
            $this->connections->save($locked);

            return $tokens->accessToken;
        });
    }
}
