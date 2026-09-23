<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Exception;

use App\Identity\Account\Domain\Account\Exception\EmailVerificationTokenExpiredException;
use App\Shared\Domain\Exception\DetailedAppException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[CoversClass(EmailVerificationTokenExpiredException::class)]
#[UsesClass(DetailedAppException::class)]
final class EmailVerificationTokenExpiredExceptionTest extends TestCase
{
    #[Test]
    public function should_expose_detailed_exception_properties(): void
    {
        // Arrange

        // Act
        $exception = new EmailVerificationTokenExpiredException();

        // Assert
        Assert::assertInstanceOf(DetailedAppException::class, $exception);
        Assert::assertSame('EMAIL_VERIFICATION_TOKEN_EXPIRED', $exception->getErrorCode());
        Assert::assertNull($exception->getField());
        Assert::assertSame([], $exception->getDetails());

        $expectedMessage = 'Email verification token has expired.';
        Assert::assertSame($expectedMessage, $exception->getMessage());
        Assert::assertSame([
            'error' => 'EMAIL_VERIFICATION_TOKEN_EXPIRED',
            'message' => $expectedMessage,
            'details' => [],
            'field' => null,
        ], $exception->toArray());
    }

    #[Test]
    public function should_have_bad_request_http_status_attribute(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(EmailVerificationTokenExpiredException::class);

        // Act
        $attributes = $reflection->getAttributes(WithHttpStatus::class);

        // Assert
        Assert::assertCount(1, $attributes);
        $attribute = $attributes[0]->newInstance();
        Assert::assertSame(Response::HTTP_BAD_REQUEST, $attribute->statusCode);
    }

    #[Test]
    public function should_have_info_log_level_attribute(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(EmailVerificationTokenExpiredException::class);

        // Act
        $attributes = $reflection->getAttributes(WithLogLevel::class);

        // Assert
        Assert::assertCount(1, $attributes);
        $attribute = $attributes[0]->newInstance();
        Assert::assertSame(LogLevel::INFO, $attribute->level);
    }

    #[Test]
    public function should_be_final_class(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(EmailVerificationTokenExpiredException::class);

        // Act
        $isFinal = $reflection->isFinal();

        // Assert
        Assert::assertTrue($isFinal);
    }
}
