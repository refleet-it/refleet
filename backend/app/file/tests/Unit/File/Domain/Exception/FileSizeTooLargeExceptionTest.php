<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Domain\Exception;

use App\File\File\Domain\Exception\FileSizeTooLargeException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileSizeTooLargeException::class)]
final class FileSizeTooLargeExceptionTest extends TestCase
{
    #[Test]
    public function should_extend_exception_and_format_message(): void
    {
        // Arrange
        $actualSize = 2048;
        $maxSize = 1024;

        // Act
        $exception = new FileSizeTooLargeException($actualSize, $maxSize);

        // Assert
        Assert::assertInstanceOf(\Exception::class, $exception);
        Assert::assertSame(
            \sprintf('File size %d bytes exceeds maximum allowed size of %d bytes', $actualSize, $maxSize),
            $exception->getMessage()
        );
    }
}
