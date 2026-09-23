<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Enum;

use App\Identity\Account\Domain\Account\Enum\AccountStatusEnum;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountStatusEnum::class)]
final class AccountStatusEnumTest extends TestCase
{
    #[Test]
    public function can_login_true_only_for_active(): void
    {
        // Act & Assert
        Assert::assertTrue(AccountStatusEnum::ACTIVE->canLogin());

        Assert::assertFalse(AccountStatusEnum::PENDING->canLogin());
        Assert::assertFalse(AccountStatusEnum::SUSPENDED->canLogin());
        Assert::assertFalse(AccountStatusEnum::DEACTIVATED->canLogin());
    }

    #[Test]
    public function is_pending_true_only_for_pending(): void
    {
        // Act & Assert
        Assert::assertTrue(AccountStatusEnum::PENDING->isPending());

        Assert::assertFalse(AccountStatusEnum::ACTIVE->isPending());
        Assert::assertFalse(AccountStatusEnum::SUSPENDED->isPending());
        Assert::assertFalse(AccountStatusEnum::DEACTIVATED->isPending());
    }

    #[Test]
    public function is_active_true_only_for_active(): void
    {
        // Act & Assert
        Assert::assertTrue(AccountStatusEnum::ACTIVE->isActive());

        Assert::assertFalse(AccountStatusEnum::PENDING->isActive());
        Assert::assertFalse(AccountStatusEnum::SUSPENDED->isActive());
        Assert::assertFalse(AccountStatusEnum::DEACTIVATED->isActive());
    }

    #[Test]
    public function is_suspended_true_only_for_suspended(): void
    {
        // Act & Assert
        Assert::assertTrue(AccountStatusEnum::SUSPENDED->isSuspended());

        Assert::assertFalse(AccountStatusEnum::PENDING->isSuspended());
        Assert::assertFalse(AccountStatusEnum::ACTIVE->isSuspended());
        Assert::assertFalse(AccountStatusEnum::DEACTIVATED->isSuspended());
    }

    #[Test]
    public function is_deactivated_true_only_for_deactivated(): void
    {
        // Act & Assert
        Assert::assertTrue(AccountStatusEnum::DEACTIVATED->isDeactivated());

        Assert::assertFalse(AccountStatusEnum::PENDING->isDeactivated());
        Assert::assertFalse(AccountStatusEnum::ACTIVE->isDeactivated());
        Assert::assertFalse(AccountStatusEnum::SUSPENDED->isDeactivated());
    }

    #[Test]
    public function backed_values_are_correct(): void
    {
        // Assert
        Assert::assertSame('pending', AccountStatusEnum::PENDING->value);
        Assert::assertSame('active', AccountStatusEnum::ACTIVE->value);
        Assert::assertSame('suspended', AccountStatusEnum::SUSPENDED->value);
        Assert::assertSame('deactivated', AccountStatusEnum::DEACTIVATED->value);
    }
}
