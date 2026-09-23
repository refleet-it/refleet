<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Domain\Exception;

use App\File\File\Domain\Exception\InvalidFileNameException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidFileNameException::class)]
final class InvalidFileNameExceptionTest extends TestCase
{
    #[Test]
    public function should_extend_base_exception_and_set_message(): void
    {
        // Act
        $exception = new InvalidFileNameException();

        // Assert
        Assert::assertInstanceOf(\Exception::class, $exception);
        Assert::assertSame(
            'File name contains invalid characters. Polish characters, spaces, dots, underscores and hyphens are allowed. Special characters like < > : " / \\ | ? * and control characters are not allowed.',
            $exception->getMessage()
        );
    }
}
