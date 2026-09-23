<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Enum;

use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RoleEnum::class)]
final class RoleEnumTest extends TestCase
{
    #[Test]
    public function to_symfony_role_returns_expected_for_each_case(): void
    {
        // Act
        $userRole = RoleEnum::USER->toSymfonyRole();
        $administratorRole = RoleEnum::ADMINISTRATOR->toSymfonyRole();

        // Assert
        Assert::assertSame('ROLE_USER', $userRole);
        Assert::assertSame('ROLE_ADMINISTRATOR', $administratorRole);
    }

    #[Test]
    public function backed_values_are_correct(): void
    {
        // Assert
        Assert::assertSame('user', RoleEnum::USER->value);
        Assert::assertSame('administrator', RoleEnum::ADMINISTRATOR->value);
    }
}
