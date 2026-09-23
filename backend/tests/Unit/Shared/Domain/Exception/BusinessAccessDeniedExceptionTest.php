<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Exception;

use App\Shared\Domain\Exception\AccessDeniedException;
use App\Shared\Domain\Exception\AppException;
use App\Shared\Domain\Exception\BusinessAccessDeniedException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BusinessAccessDeniedException::class)]
#[UsesClass(AccessDeniedException::class)]
#[UsesClass(AppException::class)]
final class BusinessAccessDeniedExceptionTest extends TestCase
{
    #[Test]
    public function should_extend_access_denied_exception_with_default_message(): void
    {
        // Act
        $exception = new BusinessAccessDeniedException();

        // Assert
        Assert::assertInstanceOf(AccessDeniedException::class, $exception);
        Assert::assertInstanceOf(AppException::class, $exception);
        Assert::assertSame('Access denied', $exception->getMessage());
        Assert::assertSame(0, $exception->getCode());
    }

    #[Test]
    public function should_allow_custom_message(): void
    {
        // Arrange
        $message = 'Business access denied due to additional restrictions';

        // Act
        $exception = new BusinessAccessDeniedException($message);

        // Assert
        Assert::assertSame($message, $exception->getMessage());
        Assert::assertSame(0, $exception->getCode());
    }
}
