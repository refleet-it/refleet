<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\User;

use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\User\UserId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountUser::class)]
final class AccountUserTest extends TestCase
{
    #[Test]
    public function exposes_same_user_id_instance(): void
    {
        // Arrange
        $userId = UserId::fromString('123e4567-e89b-12d3-a456-426614174000');
        $roles = ['ROLE_USER', 'ROLE_ADMIN'];
        $email = 'user@example.com';
        $passwordHash = '$argon2id$v=19$m=65536,t=4,p=1$hash';
        $user = new AccountUser($email, $roles, $passwordHash, $userId);

        // Act
        $returned = $user->getUserId();

        // Assert
        Assert::assertSame($userId, $returned);
    }

    #[Test]
    public function has_role_returns_true_when_role_present(): void
    {
        // Arrange
        $user = new AccountUser(
            'user@example.com',
            ['ROLE_USER', 'ROLE_MANAGER'],
            null,
            UserId::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'),
        );

        // Act & Assert
        Assert::assertTrue($user->hasRole('ROLE_USER'));
        Assert::assertTrue($user->hasRole('ROLE_MANAGER'));
    }

    #[Test]
    public function has_role_returns_false_when_role_absent(): void
    {
        // Arrange
        $user = new AccountUser(
            'user@example.com',
            ['ROLE_USER'],
            null,
            UserId::fromString('12121212-3434-5656-7878-909090909090'),
        );

        // Act & Assert
        Assert::assertFalse($user->hasRole('ROLE_ADMIN'));
        Assert::assertFalse($user->hasRole('role_user')); // strict comparison
    }
}
