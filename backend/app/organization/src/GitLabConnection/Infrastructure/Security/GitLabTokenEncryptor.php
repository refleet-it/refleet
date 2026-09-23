<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Security;

use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabTokenEncryptorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Encrypts GitLab access tokens at rest with libsodium (XSalsa20-Poly1305), so the raw
 * token never sits in the database in plaintext. The key is a 32-byte secret from
 * GITLAB_TOKEN_ENCRYPTION_KEY, decoded from base64 by Symfony's `base64:` env processor.
 */
final readonly class GitLabTokenEncryptor implements GitLabTokenEncryptorInterface
{
    public function __construct(
        #[Autowire('%env(base64:GITLAB_TOKEN_ENCRYPTION_KEY)%')]
        private string $key,
    ) {
    }

    #[\Override]
    public function encrypt(string $plaintext): string
    {
        $nonce = \random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = \sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return \base64_encode($nonce.$ciphertext);
    }

    #[\Override]
    public function decrypt(string $encoded): string
    {
        $raw = \base64_decode($encoded, true);

        if (false === $raw) {
            throw new \RuntimeException('Invalid GitLab token ciphertext encoding');
        }

        $nonce = \substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = \substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = \sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        if (false === $plaintext) {
            throw new \RuntimeException('Failed to decrypt GitLab access token');
        }

        return $plaintext;
    }
}
