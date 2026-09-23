<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Exception;

use App\Shared\Domain\Exception\AppException;
use App\Shared\Domain\Exception\DetailedAppException;
use App\Tests\Helpers\Shared\Domain\Exception\FakeDetailedAppException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DetailedAppException::class)]
#[UsesClass(AppException::class)]
final class DetailedAppExceptionTest extends TestCase
{
    #[Test]
    public function should_expose_constructor_values_and_extend_app_exception(): void
    {
        // Arrange
        $message = 'Detailed error occurred';
        $errorCode = 'DETAILED_ERROR';
        $details = ['context' => 'payment', 'retryable' => false];
        $field = 'amount';

        // Act
        $exception = new FakeDetailedAppException($message, $errorCode, $details, $field);

        // Assert
        Assert::assertInstanceOf(AppException::class, $exception);
        Assert::assertSame($message, $exception->getMessage());
        Assert::assertSame($errorCode, $exception->getErrorCode());
        Assert::assertSame($details, $exception->getDetails());
        Assert::assertSame($field, $exception->getField());
    }

    #[Test]
    public function should_default_details_to_empty_array_and_field_to_null(): void
    {
        // Arrange
        $message = 'Minimal error';
        $errorCode = 'MINIMAL_ERROR';

        // Act
        $exception = new FakeDetailedAppException($message, $errorCode);

        // Assert
        Assert::assertSame([], $exception->getDetails());
        Assert::assertNull($exception->getField());
        Assert::assertSame([
            'error' => $errorCode,
            'message' => $message,
            'details' => [],
            'field' => null,
        ], $exception->toArray());
    }

    #[Test]
    public function should_include_all_properties_in_to_array_payload(): void
    {
        // Arrange
        $message = 'Validation failed';
        $errorCode = 'VALIDATION_ERROR';
        $details = ['meta' => ['index' => 3], 'reason' => 'invalid format'];
        $field = '';

        // Act
        $exception = new FakeDetailedAppException($message, $errorCode, $details, $field);

        // Assert
        Assert::assertSame([
            'error' => 'VALIDATION_ERROR',
            'message' => 'Validation failed',
            'details' => ['meta' => ['index' => 3], 'reason' => 'invalid format'],
            'field' => '',
        ], $exception->toArray());
    }

    #[Test]
    public function should_not_mutate_internal_details_when_returned_array_is_changed(): void
    {
        // Arrange
        $exception = new FakeDetailedAppException('Error', 'ERROR', ['item' => 'original']);

        // Act
        $details = $exception->getDetails();
        $details['item'] = 'changed';

        // Assert
        Assert::assertSame(['item' => 'original'], $exception->getDetails());
    }
}
