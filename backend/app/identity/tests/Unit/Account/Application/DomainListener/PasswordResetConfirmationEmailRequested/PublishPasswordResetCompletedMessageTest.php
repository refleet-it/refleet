<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\DomainListener\PasswordResetConfirmationEmailRequested;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Application\DomainListener\PasswordResetConfirmationEmailRequested\PublishPasswordResetCompletedMessage;
use App\Identity\Account\Domain\Account\Event\PasswordResetConfirmationEmailRequested;
use App\Identity\Account\Infrastructure\Bus\PasswordResetCompleted\PasswordResetCompletedMessage;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(PublishPasswordResetCompletedMessage::class)]
final class PublishPasswordResetCompletedMessageTest extends TestCase
{
    use Factories;

    private MessageBusInterface&MockObject $messageBus;

    private PublishPasswordResetCompletedMessage $listener;

    #[Test]
    public function dispatches_password_reset_completed_message_with_payload_from_domain_event(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $email = 'Mixed.Case+alias@example.com';
        $event = PasswordResetConfirmationEmailRequested::fromPasswordResetCompleted(
            accountId: $account->id(),
            email: $email,
            occurredAt: new \DateTimeImmutable('2025-01-15T12:30:00+00:00'),
        );

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use ($account, $email): Envelope {
                Assert::assertInstanceOf(PasswordResetCompletedMessage::class, $message);
                Assert::assertSame($account->id()->asString(), $message->accountId);
                Assert::assertSame($email, $message->email);

                return new Envelope($message);
            });

        // Act
        ($this->listener)($event);

        // Assert
        Assert::assertSame($account->id()->asString(), $event->accountId()->asString());
    }

    #[Test]
    public function keeps_domain_event_data_unchanged_after_dispatching_message(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $email = 'someone+tag@example.com ';
        $occurredAt = new \DateTimeImmutable('2001-02-03T04:05:06+00:00');
        $event = PasswordResetConfirmationEmailRequested::fromPasswordReset(
            accountId: $account->id(),
            email: $email,
            occurredAt: $occurredAt,
        );

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use ($account, $email): Envelope {
                Assert::assertInstanceOf(PasswordResetCompletedMessage::class, $message);
                Assert::assertSame($account->id()->asString(), $message->accountId);
                Assert::assertSame($email, $message->email);

                return new Envelope($message);
            });

        // Act
        ($this->listener)($event);

        // Assert
        Assert::assertSame($account->id()->asString(), $event->accountId()->asString());
        Assert::assertSame($email, $event->email());
        Assert::assertSame($occurredAt->getTimestamp(), $event->occurredAt()->getTimestamp());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->listener = new PublishPasswordResetCompletedMessage(
            messageBus: $this->messageBus,
        );
    }
}
