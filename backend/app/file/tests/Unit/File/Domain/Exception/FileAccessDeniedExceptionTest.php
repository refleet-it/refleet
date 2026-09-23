<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Domain\Exception;

use App\File\File\Domain\Exception\FileAccessDeniedException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileAccessDeniedException::class)]
final class FileAccessDeniedExceptionTest extends TestCase
{
    #[Test]
    public function should_extend_exception_and_format_message(): void
    {
        // Arrange
        $fileId = 'file-123';

        // Act
        $exception = new FileAccessDeniedException($fileId);

        // Assert
        Assert::assertInstanceOf(\Exception::class, $exception);
        Assert::assertSame(\sprintf('Access denied to file with ID "%s"', $fileId), $exception->getMessage());
    }
}
