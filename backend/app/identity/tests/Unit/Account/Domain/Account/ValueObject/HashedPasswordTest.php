<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\ValueObject;

use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HashedPassword::class)]
final class HashedPasswordTest extends TestCase
{
    #[Test]
    public function should_create_from_valid_string_and_expose_value(): void
    {
        // Arrange
        $hash = 'hashed_password_123';

        // Act
        $hashedPassword = HashedPassword::fromString($hash);

        // Assert
        Assert::assertSame($hash, $hashedPassword->asString());
        Assert::assertSame($hash, (string) $hashedPassword);
    }

    #[Test]
    public function should_throw_exception_when_value_is_empty(): void
    {
        // Arrange
        $empty = '';

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Hashed password cannot be empty');
        HashedPassword::fromString($empty);
    }

    #[Test]
    public function should_be_equal_when_hashes_are_same(): void
    {
        // Arrange
        $hash = 'same_hash_value';
        $a = HashedPassword::fromString($hash);
        $b = HashedPassword::fromString($hash);

        // Act
        $result = $a->equals($b);

        // Assert
        Assert::assertTrue($result);
    }

    #[Test]
    public function should_not_be_equal_when_hashes_are_different(): void
    {
        // Arrange
        $a = HashedPassword::fromString('hash_one');
        $b = HashedPassword::fromString('hash_two');

        // Act
        $result = $a->equals($b);

        // Assert
        Assert::assertFalse($result);
    }
}
