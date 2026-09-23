<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\User;

use App\Shared\Domain\User\AuthenticatedUser;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthenticatedUser::class)]
final class AuthenticatedUserTest extends TestCase
{
    #[Test]
    public function returns_user_identifier_when_not_empty(): void
    {
        // Arrange
        $user = new AuthenticatedUser('user@example.com', ['ROLE_USER']);

        // Act
        $identifier = $user->getUserIdentifier();

        // Assert
        Assert::assertSame('user@example.com', $identifier);
    }

    #[Test]
    public function throws_when_user_identifier_is_empty(): void
    {
        // Arrange
        $user = new AuthenticatedUser('', ['ROLE_USER']);

        // Act
        $exception = null;
        try {
            $user->getUserIdentifier();
        } catch (\RuntimeException $runtimeException) {
            $exception = $runtimeException;
        }

        // Assert
        Assert::assertInstanceOf(\RuntimeException::class, $exception);
        Assert::assertSame('User identifier cannot be empty', $exception?->getMessage());
    }

    #[Test]
    public function returns_roles_without_modification(): void
    {
        // Arrange
        $roles = ['ROLE_USER', 'ROLE_USER', 'ROLE_ADMIN'];
        $user = new AuthenticatedUser('user@example.com', $roles);

        // Act
        $returnedRoles = $user->getRoles();

        // Assert
        Assert::assertSame($roles, $returnedRoles);
    }

    #[Test]
    public function returns_password_or_null_as_provided(): void
    {
        // Arrange
        $userWithPassword = new AuthenticatedUser('user@example.com', ['ROLE_USER'], '$2y$13$hash');
        $userWithoutPassword = new AuthenticatedUser('user@example.com', ['ROLE_USER']);

        // Act
        $password = $userWithPassword->getPassword();
        $nullPassword = $userWithoutPassword->getPassword();

        // Assert
        Assert::assertSame('$2y$13$hash', $password);
        Assert::assertNull($nullPassword);
    }
}
