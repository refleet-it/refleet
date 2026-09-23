<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Model;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Fixtures\Factory\Identity\EmailVerificationTokenFactory;
use App\Identity\Account\Domain\Account\Model\EmailVerificationToken;
use App\Shared\Domain\ValueObject\Id;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(EmailVerificationToken::class)]
final class EmailVerificationTokenTest extends TestCase
{
    use Factories;

    #[Test]
    public function creates_email_verification_token_with_expected_initial_state(): void
    {
        // Arrange
        $id = Id::fromString('550e8400-e29b-41d4-a716-446655440010');
        $account = AccountFactory::new()->withoutPersisting()->create();
        $token = 'email_verification_token_001';
        $expiresAt = new \DateTimeImmutable('+1 hour');

        // Act
        $emailVerificationToken = EmailVerificationTokenFactory::new()->withoutPersisting()->create([
            'id' => $id,
            'account' => $account,
            'token' => $token,
            'expiresAt' => $expiresAt,
        ]);

        // Assert
        Assert::assertTrue($emailVerificationToken->id()->equals($id));
        Assert::assertSame($account, $emailVerificationToken->account());
        Assert::assertSame($token, $emailVerificationToken->token());
        Assert::assertSame($expiresAt->getTimestamp(), $emailVerificationToken->expiresAt()->getTimestamp());
        Assert::assertFalse($emailVerificationToken->isUsed());
        Assert::assertFalse($emailVerificationToken->isExpired());
    }

    #[Test]
    public function is_expired_returns_true_when_expiration_is_in_the_past(): void
    {
        // Arrange
        $emailVerificationToken = EmailVerificationTokenFactory::new()->withoutPersisting()->create([
            'expiresAt' => new \DateTimeImmutable('-1 second'),
        ]);

        // Act
        $isExpired = $emailVerificationToken->isExpired();

        // Assert
        Assert::assertTrue($isExpired);
    }

    #[Test]
    public function is_expired_returns_false_when_expiration_is_in_the_future(): void
    {
        // Arrange
        $emailVerificationToken = EmailVerificationTokenFactory::new()->withoutPersisting()->create([
            'expiresAt' => new \DateTimeImmutable('2999-01-01T00:00:00+00:00'),
        ]);

        // Act
        $isExpired = $emailVerificationToken->isExpired();

        // Assert
        Assert::assertFalse($isExpired);
    }

    #[Test]
    public function mark_as_used_sets_flag_and_keeps_it_true_on_subsequent_calls(): void
    {
        // Arrange
        $emailVerificationToken = EmailVerificationTokenFactory::new()->withoutPersisting()->create();

        // Act
        $emailVerificationToken->markAsUsed();
        $emailVerificationToken->markAsUsed();

        // Assert
        Assert::assertTrue($emailVerificationToken->isUsed());
    }
}
