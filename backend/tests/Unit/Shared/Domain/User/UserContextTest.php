<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\User;

use App\Shared\Domain\User\UserContext;
use App\Shared\Domain\User\UserId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserContext::class)]
final class UserContextTest extends TestCase
{
    #[Test]
    public function returns_identifier_from_user_id(): void
    {
        // Arrange
        $userId = UserId::fromString('123e4567-e89b-12d3-a456-426614174000');
        $roles = ['ROLE_USER', 'ROLE_MANAGER'];
        $context = new UserContext($userId, $roles);

        // Act
        $identifier = $context->getUserIdentifier();

        // Assert
        Assert::assertSame($userId->asString(), $identifier);
    }

    #[Test]
    public function exposes_same_user_id_instance(): void
    {
        // Arrange
        $userId = UserId::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $context = new UserContext($userId, ['ROLE_USER']);

        // Act & Assert
        Assert::assertSame($userId, $context->getUserId());
    }

    #[Test]
    public function returns_roles_as_provided(): void
    {
        // Arrange
        $userId = UserId::fromString('12121212-3434-5656-7878-909090909090');
        $roles = ['ROLE_USER', 'ROLE_ADMINISTRATOR'];
        $context = new UserContext($userId, $roles);

        // Act & Assert
        Assert::assertSame($roles, $context->getRoles());
    }
}
