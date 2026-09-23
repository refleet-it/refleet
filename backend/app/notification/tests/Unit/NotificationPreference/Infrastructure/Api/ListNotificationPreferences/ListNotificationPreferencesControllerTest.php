<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\NotificationPreference\Infrastructure\Api\ListNotificationPreferences;

use App\Notification\NotificationPreference\Application\Query\GetNotificationPreferences\GetNotificationPreferencesQuery;
use App\Notification\NotificationPreference\Application\Query\GetNotificationPreferences\NotificationPreferenceOverview;
use App\Notification\NotificationPreference\Infrastructure\Api\ListNotificationPreferences\ListNotificationPreferencesController;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\User\UserId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ListNotificationPreferencesController::class)]
final class ListNotificationPreferencesControllerTest extends TestCase
{
    private MessageBusInterface&MockObject $bus;

    private AccountUser $user;

    #[Test]
    public function returns_the_dispatched_preferences(): void
    {
        // Arrange
        $overview = new NotificationPreferenceOverview(
            notificationType: 'shift_finished',
            label: 'Shift Finished',
            enabledChannels: ['email'],
        );

        $this->bus
            ->method('dispatch')
            ->with($this->callback(static function (object $message): bool {
                Assert::assertInstanceOf(GetNotificationPreferencesQuery::class, $message);

                return true;
            }))
            ->willReturnCallback(static fn ($message): Envelope => new Envelope($message, [
                new HandledStamp([$overview], 'handler'),
            ]));

        $controller = new ListNotificationPreferencesController($this->bus);

        // Act
        $response = $controller($this->user);

        // Assert
        Assert::assertSame(200, $response->getStatusCode());
        $payload = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertCount(1, $payload['preferences']);
        Assert::assertSame('shift_finished', $payload['preferences'][0]['notificationType']);
        Assert::assertSame(['email'], $payload['preferences'][0]['enabledChannels']);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->user = new AccountUser('owner@example.com', ['ROLE_USER'], null, UserId::fromString('11111111-1111-1111-1111-111111111111'));
    }
}
