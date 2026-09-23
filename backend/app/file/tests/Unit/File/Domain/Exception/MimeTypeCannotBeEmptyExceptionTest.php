<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Domain\Exception;

use App\File\File\Domain\Exception\MimeTypeCannotBeEmptyException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MimeTypeCannotBeEmptyException::class)]
final class MimeTypeCannotBeEmptyExceptionTest extends TestCase
{
    #[Test]
    public function should_extend_base_exception_and_set_message(): void
    {
        // Act
        $exception = new MimeTypeCannotBeEmptyException();

        // Assert
        Assert::assertInstanceOf(\Exception::class, $exception);
        Assert::assertSame('MIME type cannot be empty', $exception->getMessage());
    }
}
