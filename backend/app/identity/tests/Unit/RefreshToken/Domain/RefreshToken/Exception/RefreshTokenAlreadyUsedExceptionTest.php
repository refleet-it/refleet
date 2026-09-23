<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\RefreshToken\Domain\RefreshToken\Exception;

use App\Identity\RefreshToken\Domain\RefreshToken\Exception\RefreshTokenAlreadyUsedException;
use App\Shared\Domain\Exception\AppException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[CoversClass(RefreshTokenAlreadyUsedException::class)]
#[UsesClass(AppException::class)]
final class RefreshTokenAlreadyUsedExceptionTest extends TestCase
{
    #[Test]
    public function should_extend_app_exception_and_set_message(): void
    {
        // Act
        $exception = new RefreshTokenAlreadyUsedException();

        // Assert
        Assert::assertInstanceOf(AppException::class, $exception);
        Assert::assertSame('Refresh token already used', $exception->getMessage());
    }

    #[Test]
    public function should_have_unprocessable_entity_http_status_attribute(): void
    {
        // Arrange & Act
        $reflection = new \ReflectionClass(RefreshTokenAlreadyUsedException::class);
        $attributes = $reflection->getAttributes(WithHttpStatus::class);

        // Assert
        Assert::assertNotEmpty($attributes);
        $attribute = $attributes[0]->newInstance();
        Assert::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $attribute->statusCode);
    }
}
