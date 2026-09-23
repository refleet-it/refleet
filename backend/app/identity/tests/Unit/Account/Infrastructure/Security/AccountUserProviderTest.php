<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Security;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Infrastructure\Factory\AccountUserFactory;
use App\Identity\Account\Infrastructure\Security\AccountUserProvider;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\User\AuthenticatedUser;
use App\Shared\Domain\User\UserId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(AccountUserProvider::class)]
final class AccountUserProviderTest extends TestCase
{
    use Factories;

    private AccountRepositoryInterface&MockObject $accountRepository;

    private AccountUserProvider $provider;

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function refresh_user_throws_when_user_is_not_account_user(): void
    {
        // Arrange
        $user = new AuthenticatedUser('user@example.com', ['ROLE_USER'], 'hash');

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User must be an instance of AccountUser');

        // Act
        $this->provider->refreshUser($user);
    }

    #[Test]
    public function refresh_user_reloads_by_identifier_and_returns_rebuilt_user(): void
    {
        // Arrange
        $account = AccountFactory::new([
            'email' => Email::fromString('Reload.User@Example.com'),
            'role' => RoleEnum::USER,
        ])->withoutPersisting()->create();

        $initialUser = new AccountUser(
            'reload.user@example.com',
            ['ROLE_USER'],
            'stale-hash',
            UserId::generate(),
        );

        $this->accountRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('reload.user@example.com')
            ->willReturn($account);

        // Act
        $reloaded = $this->provider->refreshUser($initialUser);

        // Assert
        Assert::assertInstanceOf(AccountUser::class, $reloaded);
        Assert::assertSame($account->email(), $reloaded->getUserIdentifier());
        Assert::assertSame([$account->role()->toSymfonyRole()], $reloaded->getRoles());
        Assert::assertSame($account->passwordHash(), $reloaded->getPassword());
        Assert::assertTrue($reloaded->getUserId()->equals(UserId::fromString($account->id()->asString())));
    }

    #[Test]
    public function load_user_by_identifier_throws_when_account_does_not_exist(): void
    {
        // Arrange
        $identifier = 'missing@example.com';

        $this->accountRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($identifier)
            ->willReturn(null);

        // Assert
        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User with email "missing@example.com" not found.');

        // Act
        $this->provider->loadUserByIdentifier($identifier);
    }

    #[Test]
    public function load_user_by_identifier_returns_authenticated_user_for_existing_account(): void
    {
        // Arrange
        $account = AccountFactory::new([
            'email' => Email::fromString('Existing.User+1@Example.com'),
            'role' => RoleEnum::USER,
        ])->withoutPersisting()->create();

        $this->accountRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($account->email())
            ->willReturn($account);

        // Act
        $loaded = $this->provider->loadUserByIdentifier($account->email());

        // Assert
        Assert::assertInstanceOf(AuthenticatedUser::class, $loaded);
        Assert::assertInstanceOf(AccountUser::class, $loaded);
        Assert::assertSame($account->email(), $loaded->getUserIdentifier());
        Assert::assertSame([$account->role()->toSymfonyRole()], $loaded->getRoles());
        Assert::assertSame($account->passwordHash(), $loaded->getPassword());
        Assert::assertTrue($loaded->getUserId()->equals(UserId::fromString($account->id()->asString())));
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function supports_class_accepts_account_user_only(): void
    {
        // Act
        $supportsAccountUser = $this->provider->supportsClass(AccountUser::class);
        $supportsAuthenticatedUser = $this->provider->supportsClass(AuthenticatedUser::class);
        $supportsStdClass = $this->provider->supportsClass(\stdClass::class);

        // Assert
        Assert::assertTrue($supportsAccountUser);
        Assert::assertFalse($supportsAuthenticatedUser);
        Assert::assertFalse($supportsStdClass);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->accountRepository = $this->createMock(AccountRepositoryInterface::class);
        $this->provider = new AccountUserProvider($this->accountRepository, new AccountUserFactory());
    }
}
