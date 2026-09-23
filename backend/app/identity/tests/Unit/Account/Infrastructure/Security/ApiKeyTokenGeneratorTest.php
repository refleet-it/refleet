<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Security;

use App\Identity\Account\Infrastructure\Security\ApiKeyTokenGenerator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * This mints long-lived machine credentials, so its mistakes are the quiet kind: a token with less
 * randomness behind it still authenticates, still looks right in the UI, and nothing fails until
 * somebody guesses one. The cases below hold the entropy budget, and the agreement between what is
 * stored here and what ApiKeyAuthenticator recomputes from the Authorization header.
 */
#[CoversClass(ApiKeyTokenGenerator::class)]
final class ApiKeyTokenGeneratorTest extends TestCase
{
    /**
     * 24 random bytes rendered as hex, after the prefix. Written out rather than derived from the
     * class constants, which are private precisely so that changing them is a deliberate act.
     */
    private const int RANDOM_HEX_LENGTH = 48;

    #[Test]
    public function issues_a_token_carrying_the_full_random_budget_behind_the_prefix(): void
    {
        // Arrange
        $generator = new ApiKeyTokenGenerator();

        // Act
        $token = $generator->generate();

        // Assert
        Assert::assertStringStartsWith(ApiKeyTokenGenerator::PREFIX, $token->plainToken);

        $random = \substr($token->plainToken, \strlen(ApiKeyTokenGenerator::PREFIX));
        Assert::assertSame(self::RANDOM_HEX_LENGTH, \strlen($random));
        Assert::assertMatchesRegularExpression('/^[0-9a-f]+$/', $random);
    }

    /**
     * ApiKeyAuthenticator takes everything after "Bearer " and looks the key up by
     * hash('sha256', $token) — prefix included. Storing the hash of anything else here means no
     * issued key can ever be authenticated.
     */
    #[Test]
    public function stores_the_hash_the_authenticator_will_recompute_from_the_header(): void
    {
        // Arrange
        $generator = new ApiKeyTokenGenerator();

        // Act
        $token = $generator->generate();
        $asSentByAClient = 'Bearer '.$token->plainToken;
        $asRecomputedOnArrival = \hash('sha256', \substr($asSentByAClient, 7));

        // Assert
        Assert::assertSame($asRecomputedOnArrival, $token->hashedSecret);
    }

    // The prefix is the half we deliberately show in the UI so a key can be identified. It has to
    // be useless on its own, otherwise the listing hands out working credentials.
    #[Test]
    public function shows_a_prefix_that_identifies_the_key_without_authenticating_it(): void
    {
        // Arrange
        $generator = new ApiKeyTokenGenerator();

        // Act
        $token = $generator->generate();

        // Assert
        Assert::assertStringStartsWith($token->prefix, $token->plainToken);
        Assert::assertNotSame($token->plainToken, $token->prefix);
        Assert::assertNotSame($token->hashedSecret, \hash('sha256', $token->prefix));
    }

    #[Test]
    public function never_issues_the_same_token_twice(): void
    {
        // Arrange
        $generator = new ApiKeyTokenGenerator();

        // Act
        $first = $generator->generate();
        $second = $generator->generate();

        // Assert
        Assert::assertNotSame($first->plainToken, $second->plainToken);
        Assert::assertNotSame($first->hashedSecret, $second->hashedSecret);
    }
}
