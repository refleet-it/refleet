<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Notification\Domain\Enum;

use App\Notification\Notification\Domain\Enum\NotificationPriorityEnum;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotificationPriorityEnum::class)]
final class NotificationPriorityEnumTest extends TestCase
{
    #[Test]
    #[DataProvider('priorityValueProvider')]
    public function backed_values_are_correct(NotificationPriorityEnum $priority, string $expected): void
    {
        // Arrange

        // Act
        $actual = $priority->value;

        // Assert
        Assert::assertSame($expected, $actual);
    }

    #[Test]
    public function cases_returns_declared_priorities_in_order(): void
    {
        // Arrange

        // Act
        $cases = NotificationPriorityEnum::cases();

        // Assert
        Assert::assertSame(
            [
                NotificationPriorityEnum::INFO,
                NotificationPriorityEnum::SUCCESS,
                NotificationPriorityEnum::WARNING,
                NotificationPriorityEnum::ERROR,
            ],
            $cases,
        );
    }

    #[Test]
    #[DataProvider('priorityValueProvider')]
    public function from_returns_correct_case_for_valid_value(NotificationPriorityEnum $expected, string $value): void
    {
        // Arrange

        // Act
        $actual = NotificationPriorityEnum::from($value);

        // Assert
        Assert::assertSame($expected, $actual);
    }

    #[Test]
    public function try_from_returns_null_for_invalid_value(): void
    {
        // Arrange
        $invalidValue = 'critical';

        // Act
        $actual = NotificationPriorityEnum::tryFrom($invalidValue);

        // Assert
        Assert::assertNull($actual);
    }

    #[Test]
    public function from_throws_value_error_for_invalid_value(): void
    {
        // Arrange
        $invalidValue = 'critical';

        // Act
        $thrown = null;
        try {
            NotificationPriorityEnum::from($invalidValue);
        } catch (\ValueError $valueError) {
            $thrown = $valueError;
        }

        // Assert
        Assert::assertInstanceOf(\ValueError::class, $thrown);
    }

    /**
     * @return iterable<string, array{NotificationPriorityEnum, string}>
     */
    public static function priorityValueProvider(): iterable
    {
        yield 'info' => [NotificationPriorityEnum::INFO, 'info'];
        yield 'success' => [NotificationPriorityEnum::SUCCESS, 'success'];
        yield 'warning' => [NotificationPriorityEnum::WARNING, 'warning'];
        yield 'error' => [NotificationPriorityEnum::ERROR, 'error'];
    }
}
