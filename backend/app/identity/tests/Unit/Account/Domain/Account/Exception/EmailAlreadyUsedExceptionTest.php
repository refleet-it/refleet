<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Exception;

use App\Identity\Account\Domain\Account\Exception\EmailAlreadyUsedException;
use App\Shared\Domain\Exception\DetailedAppException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[CoversClass(EmailAlreadyUsedException::class)]
#[UsesClass(DetailedAppException::class)]
final class EmailAlreadyUsedExceptionTest extends TestCase
{
    #[Test]
    public function should_expose_detailed_exception_properties(): void
    {
        // Arrange
        $email = 'john@example.com';

        // Act
        $exception = new EmailAlreadyUsedException($email);

        // Assert
        Assert::assertSame('EMAIL_ALREADY_USED', $exception->getErrorCode());
        Assert::assertSame('email', $exception->getField());
        Assert::assertSame([
            'email' => $email,
            'suggestion' => 'Use a different email address or log in to your existing account',
        ], $exception->getDetails());

        $expectedMessage = \sprintf("Email '%s' is already in use in the system", $email);
        Assert::assertSame($expectedMessage, $exception->getMessage());
        Assert::assertSame([
            'error' => 'EMAIL_ALREADY_USED',
            'message' => $expectedMessage,
            'details' => [
                'email' => $email,
                'suggestion' => 'Use a different email address or log in to your existing account',
            ],
            'field' => 'email',
        ], $exception->toArray());
    }

    #[Test]
    public function should_have_conflict_http_status_attribute(): void
    {
        // Arrange & Act
        $reflection = new \ReflectionClass(EmailAlreadyUsedException::class);
        $attributes = $reflection->getAttributes(WithHttpStatus::class);

        // Assert
        Assert::assertNotEmpty($attributes);
        $attribute = $attributes[0]->newInstance();
        Assert::assertSame(Response::HTTP_CONFLICT, $attribute->statusCode);
    }
}
