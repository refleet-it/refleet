<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Exception;

use App\Identity\Account\Domain\Account\Exception\AccountNotActiveException;
use App\Shared\Domain\Exception\DetailedAppException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[CoversClass(AccountNotActiveException::class)]
#[UsesClass(DetailedAppException::class)]
final class AccountNotActiveExceptionTest extends TestCase
{
    #[Test]
    public function should_expose_detailed_exception_properties(): void
    {
        // Act
        $exception = new AccountNotActiveException();

        // Assert
        Assert::assertSame('ACCOUNT_NOT_ACTIVE', $exception->getErrorCode());
        Assert::assertNull($exception->getField());
        Assert::assertSame([
            'suggestion' => 'Contact the administrator to activate your account',
            'hint' => 'Your account may require email verification or may have been suspended',
        ], $exception->getDetails());

        $expectedMessage = 'Account is not active';
        Assert::assertSame($expectedMessage, $exception->getMessage());
        Assert::assertSame([
            'error' => 'ACCOUNT_NOT_ACTIVE',
            'message' => $expectedMessage,
            'details' => [
                'suggestion' => 'Contact the administrator to activate your account',
                'hint' => 'Your account may require email verification or may have been suspended',
            ],
            'field' => null,
        ], $exception->toArray());
    }

    #[Test]
    public function should_have_unprocessable_entity_http_status_attribute(): void
    {
        // Arrange & Act
        $reflection = new \ReflectionClass(AccountNotActiveException::class);
        $attributes = $reflection->getAttributes(WithHttpStatus::class);

        // Assert
        Assert::assertNotEmpty($attributes);
        $attribute = $attributes[0]->newInstance();
        Assert::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $attribute->statusCode);
    }
}
