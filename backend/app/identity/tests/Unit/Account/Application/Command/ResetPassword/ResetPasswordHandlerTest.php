<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\ResetPassword;

use App\Identity\Account\Application\Command\ResetPassword\ResetPasswordCommand;
use App\Identity\Account\Application\Command\ResetPassword\ResetPasswordHandler;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Exception\PasswordResetTokenAlreadyUsedException;
use App\Identity\Account\Domain\Account\Exception\PasswordResetTokenExpiredException;
use App\Identity\Account\Domain\Account\Exception\PasswordResetTokenNotFoundException;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Model\PasswordResetToken;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\Repository\PasswordResetTokenRepositoryInterface;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\Account\Infrastructure\Service\PasswordHasher;
use App\Shared\Domain\ValueObject\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * A reset link is a bearer credential: whoever holds it can take the account. The cases here are
 * the three refusals and, just as important, that a link cannot be spent twice.
 */
#[CoversClass(ResetPasswordHandler::class)]
#[UsesClass(ResetPasswordCommand::class)]
#[UsesClass(PasswordResetToken::class)]
#[UsesClass(Account::class)]
#[UsesClass(Id::class)]
final class ResetPasswordHandlerTest extends TestCase
{
    #[Test]
    public function refuses_a_token_it_does_not_know(): void
    {
        // Arrange
        $accounts = $this->createMock(AccountRepositoryInterface::class);
        $accounts->expects($this->never())->method('update');
        $handler = $this->handler(null, $accounts);

        // Assert
        $this->expectException(PasswordResetTokenNotFoundException::class);

        // Act
        $handler(new ResetPasswordCommand('unknown-token', 'new-password'));
    }

    #[Test]
    public function refuses_an_expired_token(): void
    {
        // Arrange
        $accounts = $this->createMock(AccountRepositoryInterface::class);
        $accounts->expects($this->never())->method('update');
        $handler = $this->handler($this->createToken(expiresAt: new \DateTimeImmutable('-1 hour')), $accounts);

        // Assert
        $this->expectException(PasswordResetTokenExpiredException::class);

        // Act
        $handler(new ResetPasswordCommand('the-token', 'new-password'));
    }

    // Without this, a link stays valid after use — and reset links live in mailboxes.
    #[Test]
    public function refuses_a_token_that_was_already_spent(): void
    {
        // Arrange
        $token = $this->createToken();
        $token->markAsUsed();

        $accounts = $this->createMock(AccountRepositoryInterface::class);
        $accounts->expects($this->never())->method('update');
        $handler = $this->handler($token, $accounts);

        // Assert
        $this->expectException(PasswordResetTokenAlreadyUsedException::class);

        // Act
        $handler(new ResetPasswordCommand('the-token', 'new-password'));
    }

    #[Test]
    public function stores_the_hashed_password_and_never_the_plain_one(): void
    {
        // Arrange
        $hasher = $this->createMock(PasswordHasher::class);
        $hasher->expects($this->once())->method('hash')->with('new-password')->willReturn('hashed-new-password');

        $saved = null;
        $accounts = $this->createMock(AccountRepositoryInterface::class);
        $accounts
            ->expects($this->once())
            ->method('update')
            ->willReturnCallback(static function (Account $account) use (&$saved): void {
                $saved = $account;
            });

        $handler = $this->handler($this->createToken(), $accounts, $hasher);

        // Act
        $handler(new ResetPasswordCommand('the-token', 'new-password'));

        // Assert
        $this->assertInstanceOf(Account::class, $saved);
        $this->assertSame('hashed-new-password', $saved->passwordHash());
    }

    #[Test]
    public function marks_the_token_as_used_so_the_link_cannot_be_replayed(): void
    {
        // Arrange
        $token = $this->createToken();
        $tokens = $this->createMock(PasswordResetTokenRepositoryInterface::class);
        $tokens->method('findByToken')->willReturn($token);
        $tokens->expects($this->once())->method('save')->with($token);

        $handler = $this->handler($token, tokens: $tokens);

        // Act
        $handler(new ResetPasswordCommand('the-token', 'new-password'));

        // Assert
        $this->assertTrue($token->isUsed());
    }

    /**
     * Doubles are built per test so each one only mocks what it asserts on; the rest stay stubs.
     */
    private function handler(
        ?PasswordResetToken $found,
        ?AccountRepositoryInterface $accounts = null,
        ?PasswordHasher $hasher = null,
        ?PasswordResetTokenRepositoryInterface $tokens = null,
    ): ResetPasswordHandler {
        if (null === $tokens) {
            $tokens = $this->createStub(PasswordResetTokenRepositoryInterface::class);
            $tokens->method('findByToken')->willReturn($found);
        }

        if (null === $hasher) {
            $hasher = $this->createStub(PasswordHasher::class);
            $hasher->method('hash')->willReturn('hashed-new-password');
        }

        return new ResetPasswordHandler(
            $tokens,
            $accounts ?? $this->createStub(AccountRepositoryInterface::class),
            $hasher,
            $this->createStub(LoggerInterface::class),
        );
    }

    private function createToken(?\DateTimeImmutable $expiresAt = null): PasswordResetToken
    {
        $account = Account::create(
            AccountId::fromString('11111111-2222-3333-4444-555555555555'),
            Email::fromString('someone@refleet.it'),
            HashedPassword::fromString('old-hash'),
            RoleEnum::USER,
        );

        return PasswordResetToken::create(
            Id::generate(),
            $account,
            'the-token',
            $expiresAt ?? new \DateTimeImmutable('+1 hour'),
        );
    }
}
