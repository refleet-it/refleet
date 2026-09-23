<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\DomainListener\PasswordResetCompleted;

use App\Identity\Account\Application\DomainListener\PasswordResetCompleted\SendPasswordResetConfirmationEmail;
use App\Identity\Account\Domain\Account\Event\PasswordResetCompleted;
use App\Identity\Account\Domain\Account\Event\PasswordResetConfirmationEmailRequested;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(SendPasswordResetConfirmationEmail::class)]
final class SendPasswordResetConfirmationEmailTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;

    private SendPasswordResetConfirmationEmail $listener;

    #[Test]
    public function dispatches_confirmation_email_requested_event(): void
    {
        // Arrange
        $accountId = AccountId::fromString('550e8400-e29b-41d4-a716-446655440042');
        $email = 'user@example.com';
        $beforeDispatch = new \DateTimeImmutable();

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event) use ($accountId, $email, $beforeDispatch): bool {
                Assert::assertInstanceOf(PasswordResetConfirmationEmailRequested::class, $event);
                Assert::assertSame($accountId, $event->accountId());
                Assert::assertSame($email, $event->email());
                Assert::assertInstanceOf(\DateTimeImmutable::class, $event->occurredAt());
                Assert::assertGreaterThanOrEqual($beforeDispatch->getTimestamp(), $event->occurredAt()->getTimestamp());
                Assert::assertLessThanOrEqual(new \DateTimeImmutable()->getTimestamp(), $event->occurredAt()->getTimestamp());

                return true;
            }))
            ->willReturnCallback(static fn ($message): Envelope => new Envelope($message));

        $listener = $this->listener;

        // Act
        $listener(new PasswordResetCompleted(
            accountId: $accountId,
            email: $email,
        ));

        // Assert
        $this->addToAssertionCount(1);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->listener = new SendPasswordResetConfirmationEmail(
            messageBus: $this->messageBus,
        );
    }
}
