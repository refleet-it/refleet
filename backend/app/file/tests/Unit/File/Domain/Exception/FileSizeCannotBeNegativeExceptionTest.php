<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Domain\Exception;

use App\File\File\Domain\Exception\FileSizeCannotBeNegativeException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileSizeCannotBeNegativeException::class)]
final class FileSizeCannotBeNegativeExceptionTest extends TestCase
{
    #[Test]
    public function should_extend_exception_and_set_default_message(): void
    {
        // Arrange

        // Act
        $exception = new FileSizeCannotBeNegativeException();

        // Assert
        Assert::assertInstanceOf(\Exception::class, $exception);
        Assert::assertSame('File size cannot be negative', $exception->getMessage());
    }
}
