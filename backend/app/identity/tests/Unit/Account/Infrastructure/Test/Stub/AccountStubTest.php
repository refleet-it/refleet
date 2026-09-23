<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Test\Stub;

use App\Identity\Account\Infrastructure\Test\Stub\AccountStub;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[CoversClass(AccountStub::class)]
final class AccountStubTest extends TestCase
{
    #[Test]
    public function returns_empty_password_by_default(): void
    {
        // Arrange
        $accountStub = new AccountStub();

        // Act
        $password = $accountStub->getPassword();

        // Assert
        Assert::assertSame('', $password);
    }

    #[Test]
    public function returns_exact_password_value_passed_to_constructor(): void
    {
        // Arrange
        $accountStub = new AccountStub(' 0pa$$ word ');

        // Act
        $password = $accountStub->getPassword();

        // Assert
        Assert::assertSame(' 0pa$$ word ', $password);
        Assert::assertInstanceOf(PasswordAuthenticatedUserInterface::class, $accountStub);
    }
}
