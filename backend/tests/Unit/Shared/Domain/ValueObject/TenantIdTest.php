<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantId::class)]
final class TenantIdTest extends TestCase
{
    #[Test]
    public function creates_from_valid_value_and_exposes_string(): void
    {
        // Arrange
        $value = 'tenant-123';

        // Act
        $tenantId = TenantId::fromString($value);

        // Assert
        Assert::assertSame($value, $tenantId->asString());
    }

    #[Test]
    public function rejects_empty_value(): void
    {
        // Arrange
        $value = '';

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TenantId cannot be empty');
        TenantId::fromString($value);
    }
}
