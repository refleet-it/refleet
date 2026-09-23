<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\ValueObject;

use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

#[CoversClass(AccountId::class)]
final class AccountIdTest extends TestCase
{
    #[Test]
    public function creates_from_valid_value_and_exposes_string(): void
    {
        // Arrange
        $value = '123e4567-e89b-12d3-a456-426614174000';

        // Act
        $id = AccountId::fromString($value);

        // Assert
        Assert::assertSame($value, $id->asString());
        Assert::assertSame($value, (string) $id);
    }

    #[Test]
    public function rejects_invalid_value(): void
    {
        // Arrange
        $value = 'not-a-valid-uuid';

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        AccountId::fromString($value);
    }

    #[Test]
    public function equals_returns_true_for_same_value(): void
    {
        // Arrange
        $a = AccountId::fromString('123e4567-e89b-12d3-a456-426614174000');
        $b = AccountId::fromString('123e4567-e89b-12d3-a456-426614174000');

        // Act & Assert
        Assert::assertTrue($a->equals($b));
        Assert::assertTrue($b->equals($a));
    }

    #[Test]
    public function equals_returns_false_for_different_values(): void
    {
        // Arrange
        $a = AccountId::fromString('123e4567-e89b-12d3-a456-426614174000');
        $b = AccountId::fromString('123e4567-e89b-12d3-a456-426614174111');

        // Act & Assert
        Assert::assertFalse($a->equals($b));
        Assert::assertFalse($b->equals($a));
    }

    #[Test]
    public function generate_produces_valid_uuid(): void
    {
        // Act
        $id = AccountId::generate();

        // Assert
        Assert::assertTrue(Uuid::isValid($id->asString()));
    }

    #[Test]
    public function generate_produces_unique_identifier_each_time(): void
    {
        // Act
        $first = AccountId::generate();
        $second = AccountId::generate();

        // Assert
        Assert::assertNotSame($first->asString(), $second->asString());
    }
}
