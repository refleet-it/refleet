<?php

declare(strict_types=1);

namespace App\Tests\Unit\Qualification\Qualification\Application\DomainListener\QualificationCompleted;

use App\Qualification\Qualification\Application\DomainListener\QualificationCompleted\PublishQualificationFinishedMessage;
use App\Qualification\Qualification\Domain\Qualification\Event\QualificationCompleted;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\AccountId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Infrastructure\Bus\QualificationFinished\QualificationFinishedMessage;
use App\Shared\Domain\Service\UserEmailProviderInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(PublishQualificationFinishedMessage::class)]
final class PublishQualificationFinishedMessageTest extends TestCase
{
    private UserEmailProviderInterface&Stub $userEmailProvider;

    private MessageBusInterface&MockObject $messageBus;

    private PublishQualificationFinishedMessage $listener;

    #[Test]
    public function publishes_finished_message_with_resolved_email(): void
    {
        $qualificationId = QualificationId::generate();
        $createdBy = AccountId::generate();

        $this->userEmailProvider->method('getEmailByUserId')->willReturn('owner@example.com');

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $message) use ($qualificationId, $createdBy): bool {
                Assert::assertInstanceOf(QualificationFinishedMessage::class, $message);
                Assert::assertSame($createdBy->asString(), $message->accountId);
                Assert::assertSame('owner@example.com', $message->email);
                Assert::assertSame($qualificationId->asString(), $message->qualificationId);
                Assert::assertSame('Bump lib', $message->title);
                Assert::assertSame('completed', $message->outcome);
                Assert::assertNull($message->cancelReason);

                return true;
            }))
            ->willReturnCallback(static fn ($message): Envelope => new Envelope($message));

        ($this->listener)(new QualificationCompleted(
            qualificationId: $qualificationId,
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

        ($this->listener)(new QualificationCompleted(
            qualificationId: QualificationId::generate(),
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
        $this->listener = new PublishQualificationFinishedMessage(
            userEmailProvider: $this->userEmailProvider,
            messageBus: $this->messageBus,
        );
    }
}
