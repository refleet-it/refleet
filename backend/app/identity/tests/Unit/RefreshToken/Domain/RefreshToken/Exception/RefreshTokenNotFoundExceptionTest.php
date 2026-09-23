<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\RefreshToken\Domain\RefreshToken\Exception;

use App\Identity\RefreshToken\Domain\RefreshToken\Exception\RefreshTokenNotFoundException;
use App\Shared\Domain\Exception\AppException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[CoversClass(RefreshTokenNotFoundException::class)]
#[UsesClass(AppException::class)]
final class RefreshTokenNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function should_extend_app_exception_and_set_message(): void
    {
        // Arrange

        // Act
        $exception = new RefreshTokenNotFoundException();

        // Assert
        Assert::assertInstanceOf(AppException::class, $exception);
        Assert::assertSame('Login token not found.', $exception->getMessage());
    }

    #[Test]
    public function should_have_unprocessable_entity_http_status_attribute(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(RefreshTokenNotFoundException::class);

        // Act
        $attributes = $reflection->getAttributes(WithHttpStatus::class);

        // Assert
        Assert::assertCount(1, $attributes);
        $attribute = $attributes[0]->newInstance();
        Assert::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $attribute->statusCode);
    }

    #[Test]
    public function should_have_info_log_level_attribute(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(RefreshTokenNotFoundException::class);

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
        $reflection = new \ReflectionClass(RefreshTokenNotFoundException::class);

        // Act
        $isFinal = $reflection->isFinal();

        // Assert
        Assert::assertTrue($isFinal);
    }
}
