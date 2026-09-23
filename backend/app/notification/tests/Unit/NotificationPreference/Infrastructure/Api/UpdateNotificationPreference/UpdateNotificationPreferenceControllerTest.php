<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\NotificationPreference\Infrastructure\Api\UpdateNotificationPreference;

use App\Notification\NotificationPreference\Application\Command\UpdateNotificationPreference\UpdateNotificationPreferenceCommand;
use App\Notification\NotificationPreference\Infrastructure\Api\UpdateNotificationPreference\UpdateNotificationPreferenceController;
use App\Notification\NotificationPreference\Infrastructure\Api\UpdateNotificationPreference\UpdateNotificationPreferenceRequest;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\User\UserId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(UpdateNotificationPreferenceController::class)]
final class UpdateNotificationPreferenceControllerTest extends TestCase
{
    private MessageBusInterface&MockObject $bus;

    private AccountUser $user;

    #[Test]
    public function dispatches_the_update_command_and_returns_no_content(): void
    {
        // Arrange
        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (object $message): bool {
                Assert::assertInstanceOf(UpdateNotificationPreferenceCommand::class, $message);
                Assert::assertSame($this->user->getUserId()->asString(), $message->userId);
                Assert::assertSame('shift_finished', $message->notificationType);
                Assert::assertSame(['email'], $message->enabledChannels);

                return true;
            }))
            ->willReturnCallback(static fn ($message): Envelope => new Envelope($message));

        $controller = new UpdateNotificationPreferenceController($this->bus);

        // Act
        $response = $controller('shift_finished', new UpdateNotificationPreferenceRequest(['email']), $this->user);

        // Assert
        Assert::assertSame(204, $response->getStatusCode());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->user = new AccountUser('owner@example.com', ['ROLE_USER'], null, UserId::fromString('11111111-1111-1111-1111-111111111111'));
    }
}
