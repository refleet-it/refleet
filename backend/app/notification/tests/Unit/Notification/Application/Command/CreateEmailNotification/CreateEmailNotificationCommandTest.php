<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Notification\Application\Command\CreateEmailNotification;

use App\Notification\Notification\Application\Command\CreateEmailNotification\CreateEmailNotificationCommand;
use App\Shared\Domain\ValueObject\Id;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateEmailNotificationCommand::class)]
#[UsesClass(Id::class)]
final class CreateEmailNotificationCommandTest extends TestCase
{
    #[Test]
    public function creates_command_with_all_fields(): void
    {
        // Arrange
        $uuid = '11111111-2222-3333-4444-555555555555';
        $id = Id::fromString($uuid);
        $email = 'user@example.com';
        $subject = 'Welcome';
        $body = 'Hello there!';

        // Act
        $command = new CreateEmailNotificationCommand(
            id: $id,
            email: $email,
            subject: $subject,
            body: $body,
        );

        // Assert
        Assert::assertSame($id, $command->id);
        Assert::assertSame($email, $command->email);
        Assert::assertSame($subject, $command->subject);
        Assert::assertSame($body, $command->body);
        Assert::assertSame($uuid, (string) $command->id);
    }

    #[Test]
    public function accepts_generated_id_and_strings(): void
    {
        // Arrange
        $id = Id::generate();
        $email = 'notify@example.org';
        $subject = 'Subject Line';
        $body = 'Email body content';

        // Act
        $command = new CreateEmailNotificationCommand(
            id: $id,
            email: $email,
            subject: $subject,
            body: $body,
        );

        // Assert
        Assert::assertInstanceOf(Id::class, $command->id);
        Assert::assertSame($email, $command->email);
        Assert::assertSame($subject, $command->subject);
        Assert::assertSame($body, $command->body);
        Assert::assertNotSame('', $command->id->asString());
    }
}
