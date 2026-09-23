<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Organization\Infrastructure\Api\CancelInvitation;

use App\Organization\Organization\Application\Command\CancelInvitation\CancelInvitationCommand;
use App\Organization\Organization\Infrastructure\Api\CancelInvitation\CancelInvitationController;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\User\UserId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(CancelInvitationController::class)]
final class CancelInvitationControllerTest extends TestCase
{
    private MessageBusInterface&MockObject $bus;

    private AccountUser $user;

    #[Test]
    public function dispatches_the_cancel_command_and_returns_no_content(): void
    {
        // Arrange
        $invitationId = '22222222-2222-2222-2222-222222222222';

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (object $message) use ($invitationId): bool {
                Assert::assertInstanceOf(CancelInvitationCommand::class, $message);
                Assert::assertSame($this->user->getUserId()->asString(), $message->requestingAccountId);
                Assert::assertSame($invitationId, $message->invitationId);

                return true;
            }))
            ->willReturnCallback(static fn ($message): Envelope => new Envelope($message));

        $controller = new CancelInvitationController($this->bus);

        // Act
        $response = $controller($invitationId, $this->user);

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
