<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Exception;

use App\Shared\Domain\Exception\AppException;
use App\Shared\Domain\Exception\ValidationException;
use App\Tests\Helpers\Shared\Domain\Exception\FakeValidationException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidationException::class)]
#[UsesClass(AppException::class)]
final class ValidationExceptionTest extends TestCase
{
    #[Test]
    public function should_extend_app_exception_with_default_message(): void
    {
        // Act
        $exception = new FakeValidationException();

        // Assert
        Assert::assertInstanceOf(AppException::class, $exception);
        Assert::assertSame('Validation failed', $exception->getMessage());
    }

    #[Test]
    public function should_allow_custom_message(): void
    {
        // Arrange
        $message = 'Invalid input provided.';

        // Act
        $exception = new FakeValidationException($message);

        // Assert
        Assert::assertSame($message, $exception->getMessage());
    }
}
