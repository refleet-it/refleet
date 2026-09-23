<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Domain\Exception;

use App\File\File\Domain\Exception\FileNotFoundException;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileNotFoundException::class)]
#[UsesClass(NotFoundException::class)]
final class FileNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function should_extend_not_found_exception_and_set_message(): void
    {
        // Arrange
        $fileId = 'file-123';

        // Act
        $exception = new FileNotFoundException($fileId);

        // Assert
        Assert::assertInstanceOf(NotFoundException::class, $exception);
        Assert::assertSame('File with ID "'.$fileId.'" not found', $exception->getMessage());
    }
}
