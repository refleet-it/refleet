<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\ReturnToAdmin;

use App\Identity\Account\Application\Command\ReturnToAdmin\ReturnToAdminCommand;
use App\Identity\Account\Application\Command\ReturnToAdmin\ReturnToAdminHandler;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Exception\AccountNotFoundException;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use App\Identity\RefreshToken\Domain\RefreshToken\Model\RefreshToken;
use App\Identity\RefreshToken\Infrastructure\Security\RefreshTokenGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Leaving an impersonation session mints a fresh admin session. The properties worth holding are
 * that it belongs to the admin, that it is no longer marked as impersonating, and that the token
 * handed back is the plain one while the account keeps only its hash.
 */
#[CoversClass(ReturnToAdminHandler::class)]
#[UsesClass(ReturnToAdminCommand::class)]
#[UsesClass(Account::class)]
#[UsesClass(RefreshTokenGenerator::class)]
final class ReturnToAdminHandlerTest extends TestCase
{
    private const string ADMIN_ID = '11111111-2222-3333-4444-555555555555';

    #[Test]
    public function refuses_when_the_admin_account_is_gone(): void
    {
        // Arrange
        $accounts = $this->createStub(AccountRepositoryInterface::class);
        $accounts->method('findById')->willReturn(null);

        $tokens = $this->createMock(TokenGenerator::class);
        $tokens->expects($this->never())->method('generate');

        $handler = $this->handler($accounts, $tokens);

        // Assert
        $this->expectException(AccountNotFoundException::class);

        // Act
        $handler(new ReturnToAdminCommand(AccountId::fromString(self::ADMIN_ID)));
    }

    #[Test]
    public function issues_the_jwt_for_the_admin_and_not_for_whoever_was_impersonated(): void
    {
        // Arrange
        $admin = $this->createAdmin();
        $accounts = $this->createStub(AccountRepositoryInterface::class);
        $accounts->method('findById')->willReturn($admin);

        $tokens = $this->createMock(TokenGenerator::class);
        $tokens
            ->expects($this->once())
            ->method('generate')
            ->with($admin)
            ->willReturn('admin-jwt');

        $handler = $this->handler($accounts, $tokens);

        // Act
        $result = $handler(new ReturnToAdminCommand(AccountId::fromString(self::ADMIN_ID)));

        // Assert
        $this->assertSame('admin-jwt', $result->jwtToken);
    }

    // Returning the hash would hand the caller something useless; storing the plain token would
    // put a working credential in the database. The endpoint also exists to stop impersonating, so
    // the stored token must carry no impersonator — otherwise the admin stays inside the session
    // they asked to leave.
    #[Test]
    public function stores_only_the_hash_of_the_token_it_hands_back_and_drops_the_impersonator(): void
    {
        // Arrange
        $admin = $this->createMock(Account::class);
        $accounts = $this->createStub(AccountRepositoryInterface::class);
        $accounts->method('findById')->willReturn($admin);

        // The handler logs the admin id, and AccountId is final so PHPUnit cannot invent one.
        $admin->method('id')->willReturn(AccountId::fromString(self::ADMIN_ID));

        $storedToken = null;
        $storedImpersonator = 'not-called';
        $admin
            ->expects($this->once())
            ->method('generateRefreshToken')
            ->willReturnCallback(
                static function (string $hashedToken, \DateTimeImmutable $expiresAt, ?string $impersonatorId = null) use (&$storedToken, &$storedImpersonator): RefreshToken {
                    $storedToken = $hashedToken;
                    $storedImpersonator = $impersonatorId;

                    return self::createStub(RefreshToken::class);
                }
            );

        $handler = $this->handler($accounts);

        // Act
        $result = $handler(new ReturnToAdminCommand(AccountId::fromString(self::ADMIN_ID)));

        // Assert
        $this->assertNotSame($result->refreshToken, $storedToken);
        $this->assertSame(\hash('sha256', (string) $result->refreshToken), $storedToken);
        $this->assertNull($storedImpersonator);
    }

    private function handler(
        AccountRepositoryInterface $accounts,
        ?TokenGenerator $tokens = null,
    ): ReturnToAdminHandler {
        if (null === $tokens) {
            $tokens = $this->createStub(TokenGenerator::class);
            $tokens->method('generate')->willReturn('admin-jwt');
        }

        return new ReturnToAdminHandler(
            $accounts,
            $tokens,
            new RefreshTokenGenerator(),
            $this->createStub(LoggerInterface::class),
        );
    }

    private function createAdmin(): Account
    {
        return Account::create(
            AccountId::fromString(self::ADMIN_ID),
            Email::fromString('admin@refleet.it'),
            HashedPassword::fromString('hashed-password'),
            RoleEnum::ADMINISTRATOR,
        );
    }
}
