<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use App\Shared\Domain\Exception\InvalidPaginationParametersException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidPaginationParametersException::class)]
final class InvalidPaginationParametersExceptionTest extends TestCase
{
    #[Test]
    public function should_extend_detailed_app_exception(): void
    {
        // Act
        $exception = new InvalidPaginationParametersException('Test message');

        // Assert
        Assert::assertInstanceOf(DetailedAppException::class, $exception);
        Assert::assertInstanceOf(\Exception::class, $exception);
    }

    #[Test]
    public function should_allow_custom_message(): void
    {
        // Arrange
        $message = 'Limit must be between 1 and 100';

        // Act
        $exception = new InvalidPaginationParametersException($message);

        // Assert
        Assert::assertSame($message, $exception->getMessage());
    }

    #[Test]
    public function should_have_proper_error_code(): void
    {
        // Arrange
        $message = 'Invalid pagination parameters';

        // Act
        $exception = new InvalidPaginationParametersException($message);

        // Assert
        Assert::assertSame('INVALID_PAGINATION_PARAMETERS', $exception->getErrorCode());
    }

    #[Test]
    public function should_convert_to_array(): void
    {
        // Arrange
        $message = 'Limit must be between 1 and 100';

        // Act
        $exception = new InvalidPaginationParametersException($message);
        $array = $exception->toArray();

        // Assert
        Assert::assertSame('INVALID_PAGINATION_PARAMETERS', $array['error']);
        Assert::assertSame($message, $array['message']);
        Assert::assertSame([], $array['details']);
        Assert::assertNull($array['field']);
    }
}
