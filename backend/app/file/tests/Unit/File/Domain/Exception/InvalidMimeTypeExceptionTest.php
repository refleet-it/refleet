<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Domain\Exception;

use App\File\File\Domain\Exception\InvalidMimeTypeException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidMimeTypeException::class)]
final class InvalidMimeTypeExceptionTest extends TestCase
{
    #[Test]
    public function should_extend_base_exception_and_set_message(): void
    {
        // Arrange
        $input = 'application/json';

        // Act
        $exception = new InvalidMimeTypeException($input);

        // Assert
        Assert::assertInstanceOf(\Exception::class, $exception);
        Assert::assertSame('MIME type "'.$input.'" is not allowed', $exception->getMessage());
    }
}
