<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Organization\Infrastructure\Api\ListPendingInvitations;

use App\Organization\Organization\Application\Query\ListPendingInvitations\ListPendingInvitationsQuery;
use App\Organization\Organization\Application\Query\ListPendingInvitations\PendingInvitationOverview;
use App\Organization\Organization\Infrastructure\Api\ListPendingInvitations\ListPendingInvitationsController;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ListPendingInvitationsController::class)]
final class ListPendingInvitationsControllerTest extends TestCase
{
    private MessageBusInterface&MockObject $bus;

    private AccountUser $user;

    #[Test]
    public function returns_the_dispatched_pending_invitations(): void
    {
        // Arrange
        $overview = new PendingInvitationOverview(
            id: 'invitation-1',
            email: 'invitee@example.com',
            createdAt: '2026-08-01T00:00:00+00:00',
            expiresAt: '2026-08-08T00:00:00+00:00',
        );

        $this->bus
            ->method('dispatch')
            ->with($this->callback(function (object $message): bool {
                Assert::assertInstanceOf(ListPendingInvitationsQuery::class, $message);
                Assert::assertSame($this->user->getUserId()->asString(), $message->requestingAccountId);

                return true;
            }))
            ->willReturnCallback(static fn ($message): Envelope => new Envelope($message, [
                new HandledStamp(ListResponse::create([$overview], 1, PaginationParameters::fromRequest()), 'handler'),
            ]));

        $controller = new ListPendingInvitationsController($this->bus);

        // Act
        $response = $controller($this->user, new Request());

        // Assert
        Assert::assertSame(200, $response->getStatusCode());
        $payload = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertCount(1, $payload['invitations']);
        Assert::assertSame('invitee@example.com', $payload['invitations'][0]['email']);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->user = new AccountUser('owner@example.com', ['ROLE_USER'], null, UserId::fromString('11111111-1111-1111-1111-111111111111'));
    }
}
