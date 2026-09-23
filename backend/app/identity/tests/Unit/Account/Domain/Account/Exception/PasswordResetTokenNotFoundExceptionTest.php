<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Exception;

use App\Identity\Account\Domain\Account\Exception\PasswordResetTokenNotFoundException;
use App\Shared\Domain\Exception\AppException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[CoversClass(PasswordResetTokenNotFoundException::class)]
#[UsesClass(AppException::class)]
final class PasswordResetTokenNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function should_extend_app_exception_and_set_message(): void
    {
        // Act
        $exception = new PasswordResetTokenNotFoundException();

        // Assert
        Assert::assertInstanceOf(AppException::class, $exception);
        Assert::assertSame('Password reset token not found.', $exception->getMessage());
    }

    #[Test]
    public function should_have_not_found_http_status_attribute(): void
    {
        // Arrange & Act
        $reflection = new \ReflectionClass(PasswordResetTokenNotFoundException::class);
        $attributes = $reflection->getAttributes(WithHttpStatus::class);

        // Assert
        Assert::assertNotEmpty($attributes);
        $attribute = $attributes[0]->newInstance();
        Assert::assertSame(Response::HTTP_NOT_FOUND, $attribute->statusCode);
    }
}
