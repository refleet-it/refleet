<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Exception;

use App\Shared\Domain\Exception\InvalidFilterCriteriaException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidFilterCriteriaException::class)]
final class InvalidFilterCriteriaExceptionTest extends TestCase
{
    #[Test]
    public function should_extend_base_exception(): void
    {
        // Act
        $exception = new InvalidFilterCriteriaException();

        // Assert
        Assert::assertInstanceOf(\Exception::class, $exception);
    }

    #[Test]
    public function should_allow_custom_message(): void
    {
        // Arrange
        $message = 'Filter field cannot be empty';

        // Act
        $exception = new InvalidFilterCriteriaException($message);

        // Assert
        Assert::assertSame($message, $exception->getMessage());
    }
}
