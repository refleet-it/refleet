<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Exception;

use App\Identity\Account\Domain\Account\Exception\ImpersonationNotAllowedException;
use App\Shared\Domain\Exception\DetailedAppException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[CoversClass(ImpersonationNotAllowedException::class)]
#[UsesClass(DetailedAppException::class)]
final class ImpersonationNotAllowedExceptionTest extends TestCase
{
    #[Test]
    public function should_expose_detailed_exception_properties(): void
    {
        // Arrange
        $message = 'Impersonation attempt is not allowed';

        // Act
        $exception = new ImpersonationNotAllowedException($message);

        // Assert
        Assert::assertSame('IMPERSONATION_NOT_ALLOWED', $exception->getErrorCode());
        Assert::assertNull($exception->getField());
        Assert::assertSame([
            'suggestion' => 'Check the permissions to impersonate this user',
            'hint' => 'Only administrators can impersonate users who are not administrators',
        ], $exception->getDetails());
        Assert::assertSame($message, $exception->getMessage());
        Assert::assertSame([
            'error' => 'IMPERSONATION_NOT_ALLOWED',
            'message' => $message,
            'details' => [
                'suggestion' => 'Check the permissions to impersonate this user',
                'hint' => 'Only administrators can impersonate users who are not administrators',
            ],
            'field' => null,
        ], $exception->toArray());
    }

    #[Test]
    public function should_have_unprocessable_entity_http_status_attribute(): void
    {
        // Arrange & Act
        $reflection = new \ReflectionClass(ImpersonationNotAllowedException::class);
        $attributes = $reflection->getAttributes(WithHttpStatus::class);

        // Assert
        Assert::assertNotEmpty($attributes);
        $attribute = $attributes[0]->newInstance();
        Assert::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $attribute->statusCode);
    }
}
