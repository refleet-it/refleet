<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Exception;

use App\Identity\Account\Domain\Account\Exception\InvalidCredentialsException;
use App\Shared\Domain\Exception\DetailedAppException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[CoversClass(InvalidCredentialsException::class)]
#[UsesClass(DetailedAppException::class)]
final class InvalidCredentialsExceptionTest extends TestCase
{
    #[Test]
    public function should_expose_detailed_exception_properties(): void
    {
        // Act
        $exception = new InvalidCredentialsException();

        // Assert
        Assert::assertSame('INVALID_CREDENTIALS', $exception->getErrorCode());
        Assert::assertNull($exception->getField());
        Assert::assertSame([
            'suggestion' => 'Check the correctness of the entered data and try again',
            'hint' => 'Make sure you are using the correct email address and password',
        ], $exception->getDetails());

        $expectedMessage = 'Invalid email or password';
        Assert::assertSame($expectedMessage, $exception->getMessage());
        Assert::assertSame([
            'error' => 'INVALID_CREDENTIALS',
            'message' => $expectedMessage,
            'details' => [
                'suggestion' => 'Check the correctness of the entered data and try again',
                'hint' => 'Make sure you are using the correct email address and password',
            ],
            'field' => null,
        ], $exception->toArray());
    }

    #[Test]
    public function should_have_unprocessable_entity_http_status_attribute(): void
    {
        // Arrange & Act
        $reflection = new \ReflectionClass(InvalidCredentialsException::class);
        $attributes = $reflection->getAttributes(WithHttpStatus::class);

        // Assert
        Assert::assertNotEmpty($attributes);
        $attribute = $attributes[0]->newInstance();
        Assert::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $attribute->statusCode);
    }
}
