<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Domain\Exception;

use App\File\File\Domain\Exception\FileNameTooLongException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileNameTooLongException::class)]
final class FileNameTooLongExceptionTest extends TestCase
{
    #[Test]
    public function should_extend_base_exception_and_set_message(): void
    {
        // Act
        $exception = new FileNameTooLongException();

        // Assert
        Assert::assertInstanceOf(\Exception::class, $exception);
        Assert::assertSame('File name cannot be longer than 255 characters', $exception->getMessage());
    }
}
