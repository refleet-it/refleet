<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Notification\Domain\Enum;

use App\Notification\Notification\Domain\Enum\NotificationTypeEnum;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotificationTypeEnum::class)]
final class NotificationTypeEnumTest extends TestCase
{
    #[Test]
    public function is_email_returns_true_only_for_email_case(): void
    {
        // Act
        $emailIsEmail = NotificationTypeEnum::EMAIL->isEmail();
        $smsIsEmail = NotificationTypeEnum::SMS->isEmail();
        $pushIsEmail = NotificationTypeEnum::PUSH->isEmail();
        $slackIsEmail = NotificationTypeEnum::SLACK->isEmail();

        // Assert
        Assert::assertTrue($emailIsEmail);
        Assert::assertFalse($smsIsEmail);
        Assert::assertFalse($pushIsEmail);
        Assert::assertFalse($slackIsEmail);
    }

    #[Test]
    public function backed_values_are_correct(): void
    {
        // Assert
        Assert::assertSame('email', NotificationTypeEnum::EMAIL->value);
        Assert::assertSame('sms', NotificationTypeEnum::SMS->value);
        Assert::assertSame('push', NotificationTypeEnum::PUSH->value);
        Assert::assertSame('slack', NotificationTypeEnum::SLACK->value);
    }
}
