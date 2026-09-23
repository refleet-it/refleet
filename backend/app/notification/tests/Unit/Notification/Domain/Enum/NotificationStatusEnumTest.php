<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Notification\Domain\Enum;

use App\Notification\Notification\Domain\Enum\NotificationStatusEnum;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotificationStatusEnum::class)]
final class NotificationStatusEnumTest extends TestCase
{
    #[Test]
    #[DataProvider('statusValueProvider')]
    public function backed_values_are_correct(NotificationStatusEnum $status, string $expected): void
    {
        // Assert
        Assert::assertSame($expected, $status->value);
    }

    #[Test]
    public function cases_returns_declared_statuses_in_order(): void
    {
        // Assert
        Assert::assertSame(
            [
                NotificationStatusEnum::PENDING,
                NotificationStatusEnum::SENT,
                NotificationStatusEnum::FAILED,
                NotificationStatusEnum::CANCELLED,
            ],
            NotificationStatusEnum::cases(),
        );
    }

    #[Test]
    #[DataProvider('statusValueProvider')]
    public function from_returns_correct_case_for_valid_value(NotificationStatusEnum $expected, string $value): void
    {
        // Act
        $result = NotificationStatusEnum::from($value);

        // Assert
        Assert::assertSame($expected, $result);
    }

    #[Test]
    public function try_from_returns_null_for_invalid_value(): void
    {
        // Act
        $result = NotificationStatusEnum::tryFrom('unknown');

        // Assert
        Assert::assertNull($result);
    }

    /**
     * @return iterable<string, array{NotificationStatusEnum, string}>
     */
    public static function statusValueProvider(): iterable
    {
        yield 'pending' => [NotificationStatusEnum::PENDING, 'pending'];
        yield 'sent' => [NotificationStatusEnum::SENT, 'sent'];
        yield 'failed' => [NotificationStatusEnum::FAILED, 'failed'];
        yield 'cancelled' => [NotificationStatusEnum::CANCELLED, 'cancelled'];
    }
}
