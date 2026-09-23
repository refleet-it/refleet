<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\GitLabConnection\Infrastructure\Security;

use App\Organization\GitLabConnection\Infrastructure\Security\GitLabTokenEncryptor;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GitLabTokenEncryptor::class)]
final class GitLabTokenEncryptorTest extends TestCase
{
    #[Test]
    public function decrypts_back_to_the_original_plaintext(): void
    {
        // Arrange
        $encryptor = new GitLabTokenEncryptor(\sodium_crypto_secretbox_keygen());

        // Act
        $ciphertext = $encryptor->encrypt('glpat-super-secret-token');

        // Assert
        Assert::assertNotSame('glpat-super-secret-token', $ciphertext);
        Assert::assertSame('glpat-super-secret-token', $encryptor->decrypt($ciphertext));
    }

    #[Test]
    public function produces_a_different_ciphertext_each_time_due_to_the_random_nonce(): void
    {
        // Arrange
        $encryptor = new GitLabTokenEncryptor(\sodium_crypto_secretbox_keygen());

        // Act
        $first = $encryptor->encrypt('glpat-super-secret-token');
        $second = $encryptor->encrypt('glpat-super-secret-token');

        // Assert
        Assert::assertNotSame($first, $second);
    }

    #[Test]
    public function throws_when_the_ciphertext_was_tampered_with(): void
    {
        // Arrange
        $encryptor = new GitLabTokenEncryptor(\sodium_crypto_secretbox_keygen());
        $ciphertext = $encryptor->encrypt('glpat-super-secret-token');

        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $encryptor->decrypt(\substr($ciphertext, 0, -4).'abcd');
    }

    #[Test]
    public function fails_to_decrypt_with_a_different_key(): void
    {
        // Arrange
        $ciphertext = (new GitLabTokenEncryptor(\sodium_crypto_secretbox_keygen()))->encrypt('glpat-super-secret-token');
        $otherEncryptor = new GitLabTokenEncryptor(\sodium_crypto_secretbox_keygen());

        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $otherEncryptor->decrypt($ciphertext);
    }
}
