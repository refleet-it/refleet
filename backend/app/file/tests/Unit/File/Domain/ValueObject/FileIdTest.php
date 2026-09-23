<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Domain\ValueObject;

use App\File\File\Domain\ValueObject\FileId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

#[CoversClass(FileId::class)]
final class FileIdTest extends TestCase
{
    private const string VALID_UUID = '123e4567-e89b-12d3-a456-426614174000';

    private const string ANOTHER_VALID_UUID = '123e4567-e89b-12d3-a456-426614174111';

    private const string INVALID_UUID = 'not-a-valid-uuid';

    private const string UPPERCASE_VALID_UUID = '123E4567-E89B-12D3-A456-426614174222';

    #[Test]
    public function creates_from_valid_value_and_exposes_string(): void
    {
        // Arrange
        $value = self::VALID_UUID;

        // Act
        $id = FileId::fromString($value);

        // Assert
        Assert::assertSame($value, $id->asString());
        Assert::assertSame($value, (string) $id);
    }

    #[Test]
    public function creates_from_uppercase_value_and_preserves_format(): void
    {
        // Arrange
        $value = self::UPPERCASE_VALID_UUID;

        // Act
        $id = FileId::fromString($value);

        // Assert
        Assert::assertSame($value, $id->asString());
        Assert::assertSame($value, (string) $id);
    }

    #[Test]
    #[DataProvider('invalidUuidProvider')]
    public function rejects_invalid_value(string $value): void
    {
        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        FileId::fromString($value);
    }

    #[Test]
    public function equals_returns_true_for_same_value(): void
    {
        // Arrange
        $a = FileId::fromString(self::VALID_UUID);
        $b = FileId::fromString(self::VALID_UUID);

        // Act & Assert
        Assert::assertTrue($a->equals($b));
        Assert::assertTrue($b->equals($a));
    }

    #[Test]
    public function equals_returns_false_for_different_values(): void
    {
        // Arrange
        $a = FileId::fromString(self::VALID_UUID);
        $b = FileId::fromString(self::ANOTHER_VALID_UUID);

        // Act & Assert
        Assert::assertFalse($a->equals($b));
        Assert::assertFalse($b->equals($a));
    }

    #[Test]
    public function generate_produces_valid_uuid7(): void
    {
        // Act
        $id = FileId::generate();

        // Assert
        Assert::assertInstanceOf(FileId::class, $id);
        Assert::assertTrue(Uuid::isValid($id->asString()));
        Assert::assertSame(7, Uuid::fromString($id->asString())->getVersion());
    }

    #[Test]
    public function generate_produces_unique_identifier_each_time(): void
    {
        // Act
        $first = FileId::generate();
        $second = FileId::generate();

        // Assert
        Assert::assertNotSame($first->asString(), $second->asString());
    }

    public static function invalidUuidProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'malformed string' => [self::INVALID_UUID];
        yield 'missing hyphen' => ['123e4567e89b12d3a456426614174000'];
    }
}
