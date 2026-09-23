<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Application\DomainListener\ShiftCompleted;

use App\Shared\Domain\Service\UserEmailProviderInterface;
use App\Shift\Shift\Application\DomainListener\ShiftCompleted\PublishShiftFinishedMessage;
use App\Shift\Shift\Domain\Shift\Event\ShiftCompleted;
use App\Shift\Shift\Domain\Shift\ValueObject\AccountId;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Infrastructure\Bus\ShiftFinished\ShiftFinishedMessage;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(PublishShiftFinishedMessage::class)]
final class PublishShiftFinishedMessageTest extends TestCase
{
    private UserEmailProviderInterface&Stub $userEmailProvider;

    private MessageBusInterface&MockObject $messageBus;

    private PublishShiftFinishedMessage $listener;

    #[Test]
    public function publishes_finished_message_with_resolved_email(): void
    {
        $shiftId = ShiftId::generate();
        $createdBy = AccountId::generate();

        $this->userEmailProvider->method('getEmailByUserId')->willReturn('owner@example.com');

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $message) use ($shiftId, $createdBy): bool {
                Assert::assertInstanceOf(ShiftFinishedMessage::class, $message);
                Assert::assertSame($createdBy->asString(), $message->accountId);
                Assert::assertSame('owner@example.com', $message->email);
                Assert::assertSame($shiftId->asString(), $message->shiftId);
                Assert::assertSame('Bump lib', $message->title);
                Assert::assertSame('completed', $message->outcome);
                Assert::assertNull($message->cancelReason);

                return true;
            }))
            ->willReturnCallback(static fn ($message): Envelope => new Envelope($message));

        ($this->listener)(new ShiftCompleted(
            shiftId: $shiftId,
            organizationId: OrganizationId::generate(),
            createdBy: $createdBy,
            title: 'Bump lib',
        ));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function does_nothing_when_creator_email_cannot_be_resolved(): void
    {
        $this->userEmailProvider->method('getEmailByUserId')->willReturn(null);
        $this->messageBus->expects($this->never())->method('dispatch');

        ($this->listener)(new ShiftCompleted(
            shiftId: ShiftId::generate(),
            organizationId: OrganizationId::generate(),
            createdBy: AccountId::generate(),
            title: 'Bump lib',
        ));

        $this->addToAssertionCount(1);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->userEmailProvider = $this->createStub(UserEmailProviderInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->listener = new PublishShiftFinishedMessage(
            userEmailProvider: $this->userEmailProvider,
            messageBus: $this->messageBus,
        );
    }
}
