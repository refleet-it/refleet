<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Exception;

use App\Shared\Domain\Exception\InvalidSortFieldException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidSortFieldException::class)]
final class InvalidSortFieldExceptionTest extends TestCase
{
    #[Test]
    public function should_extend_base_exception(): void
    {
        // Act
        $exception = new InvalidSortFieldException();

        // Assert
        Assert::assertInstanceOf(\Exception::class, $exception);
    }

    #[Test]
    public function should_allow_custom_message(): void
    {
        // Arrange
        $message = 'Invalid sort field provided';

        // Act
        $exception = new InvalidSortFieldException($message);

        // Assert
        Assert::assertSame($message, $exception->getMessage());
    }

    #[Test]
    public function should_accept_code_and_previous_exception(): void
    {
        // Arrange
        $message = 'Invalid sort field';
        $code = 42;
        $previous = new \Exception('previous');

        // Act
        $exception = new InvalidSortFieldException($message, $code, $previous);

        // Assert
        Assert::assertSame($message, $exception->getMessage());
        Assert::assertSame($code, $exception->getCode());
        Assert::assertSame($previous, $exception->getPrevious());
    }
}
