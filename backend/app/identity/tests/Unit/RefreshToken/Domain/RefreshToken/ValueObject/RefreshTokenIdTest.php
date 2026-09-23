<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\RefreshToken\Domain\RefreshToken\ValueObject;

use App\Identity\RefreshToken\Domain\RefreshToken\ValueObject\RefreshTokenId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

#[CoversClass(RefreshTokenId::class)]
final class RefreshTokenIdTest extends TestCase
{
    #[Test]
    public function creates_from_valid_value_and_exposes_string(): void
    {
        // Arrange
        $value = '123e4567-e89b-12d3-a456-426614174000';

        // Act
        $id = RefreshTokenId::fromString($value);

        // Assert
        Assert::assertSame($value, $id->asString());
        Assert::assertSame($value, (string) $id);
    }

    #[Test]
    public function creates_from_uppercase_value_and_preserves_format(): void
    {
        // Arrange
        $value = '123E4567-E89B-12D3-A456-426614174111';

        // Act
        $id = RefreshTokenId::fromString($value);

        // Assert
        Assert::assertSame($value, $id->asString());
        Assert::assertSame($value, (string) $id);
    }

    #[Test]
    public function rejects_invalid_value(): void
    {
        // Arrange
        $value = 'not-a-valid-uuid';

        // Act
        try {
            RefreshTokenId::fromString($value);
            Assert::fail('Expected InvalidArgumentException to be thrown.');
        } catch (\Throwable $throwable) {
            // Assert
            Assert::assertInstanceOf(\InvalidArgumentException::class, $throwable);
        }
    }

    #[Test]
    public function rejects_empty_value(): void
    {
        // Arrange
        $value = '';

        // Act
        try {
            RefreshTokenId::fromString($value);
            Assert::fail('Expected InvalidArgumentException to be thrown.');
        } catch (\Throwable $throwable) {
            // Assert
            Assert::assertInstanceOf(\InvalidArgumentException::class, $throwable);
        }
    }

    #[Test]
    public function equals_returns_true_for_same_value(): void
    {
        // Arrange
        $a = RefreshTokenId::fromString('123e4567-e89b-12d3-a456-426614174000');
        $b = RefreshTokenId::fromString('123e4567-e89b-12d3-a456-426614174000');

        // Act
        $equalsAB = $a->equals($b);
        $equalsBA = $b->equals($a);

        // Assert
        Assert::assertTrue($equalsAB);
        Assert::assertTrue($equalsBA);
    }

    #[Test]
    public function equals_returns_false_for_different_values(): void
    {
        // Arrange
        $a = RefreshTokenId::fromString('123e4567-e89b-12d3-a456-426614174000');
        $b = RefreshTokenId::fromString('123e4567-e89b-12d3-a456-426614174111');

        // Act
        $equalsAB = $a->equals($b);
        $equalsBA = $b->equals($a);

        // Assert
        Assert::assertFalse($equalsAB);
        Assert::assertFalse($equalsBA);
    }

    #[Test]
    public function generate_produces_valid_uuid_v7_identifier(): void
    {
        // Arrange
        $id = RefreshTokenId::generate();

        // Act
        $value = $id->asString();

        // Assert
        Assert::assertTrue(Uuid::isValid($value));
        Assert::assertSame(7, Uuid::fromString($value)->getVersion());
    }

    #[Test]
    public function generate_produces_unique_identifier_each_time(): void
    {
        // Arrange
        $first = RefreshTokenId::generate();
        $second = RefreshTokenId::generate();

        // Act
        $firstValue = $first->asString();
        $secondValue = $second->asString();

        // Assert
        Assert::assertNotSame($firstValue, $secondValue);
    }
}
