<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Domain\Exception;

use App\File\File\Domain\Exception\FileStorageException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileStorageException::class)]
final class FileStorageExceptionTest extends TestCase
{
    #[Test]
    public function should_use_default_message_and_code(): void
    {
        // Act
        $exception = new FileStorageException();

        // Assert
        Assert::assertInstanceOf(\Exception::class, $exception);
        Assert::assertSame('File storage error occurred', $exception->getMessage());
        Assert::assertSame(0, $exception->getCode());
        Assert::assertNull($exception->getPrevious());
    }

    #[Test]
    public function should_accept_custom_message_code_and_previous(): void
    {
        // Arrange
        $previous = new \RuntimeException('Disk full');

        // Act
        $exception = new FileStorageException('Custom failure', 123, $previous);

        // Assert
        Assert::assertSame('Custom failure', $exception->getMessage());
        Assert::assertSame(123, $exception->getCode());
        Assert::assertSame($previous, $exception->getPrevious());
    }
}
