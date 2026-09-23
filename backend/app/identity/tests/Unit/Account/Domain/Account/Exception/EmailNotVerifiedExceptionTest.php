<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Exception;

use App\Identity\Account\Domain\Account\Exception\EmailNotVerifiedException;
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

#[CoversClass(EmailNotVerifiedException::class)]
#[UsesClass(DetailedAppException::class)]
final class EmailNotVerifiedExceptionTest extends TestCase
{
    #[Test]
    public function should_expose_detailed_exception_properties(): void
    {
        // Arrange

        // Act
        $exception = new EmailNotVerifiedException();

        // Assert
        Assert::assertInstanceOf(DetailedAppException::class, $exception);
        Assert::assertSame('EMAIL_NOT_VERIFIED', $exception->getErrorCode());
        Assert::assertNull($exception->getField());
        Assert::assertSame([], $exception->getDetails());

        $expectedMessage = 'Email address has not been verified. Please check your email for verification link.';
        Assert::assertSame($expectedMessage, $exception->getMessage());
        Assert::assertSame([
            'error' => 'EMAIL_NOT_VERIFIED',
            'message' => $expectedMessage,
            'details' => [],
            'field' => null,
        ], $exception->toArray());
    }

    #[Test]
    public function should_have_forbidden_http_status_attribute(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(EmailNotVerifiedException::class);

        // Act
        $attributes = $reflection->getAttributes(WithHttpStatus::class);

        // Assert
        Assert::assertCount(1, $attributes);
        $attribute = $attributes[0]->newInstance();
        Assert::assertSame(Response::HTTP_FORBIDDEN, $attribute->statusCode);
    }

    #[Test]
    public function should_have_info_log_level_attribute(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(EmailNotVerifiedException::class);

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
        $reflection = new \ReflectionClass(EmailNotVerifiedException::class);

        // Act
        $isFinal = $reflection->isFinal();

        // Assert
        Assert::assertTrue($isFinal);
    }
}
