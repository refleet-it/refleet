<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\DomainListener\PasswordResetRequested;

use App\Identity\Account\Application\DomainListener\PasswordResetRequested\SendPasswordResetEmail;
use App\Identity\Account\Domain\Account\Event\PasswordResetEmailRequested;
use App\Identity\Account\Domain\Account\Event\PasswordResetRequested;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(SendPasswordResetEmail::class)]
final class SendPasswordResetEmailTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;

    private SendPasswordResetEmail $listener;

    #[Test]
    public function dispatches_password_reset_email_requested_event(): void
    {
        // Arrange
        $accountId = AccountId::fromString('550e8400-e29b-41d4-a716-446655440042');
        $email = 'user@example.com';
        $resetToken = 'reset-token';
        $beforeDispatch = new \DateTimeImmutable();

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event) use ($accountId, $email, $resetToken, $beforeDispatch): bool {
                Assert::assertInstanceOf(PasswordResetEmailRequested::class, $event);
                Assert::assertSame($accountId, $event->accountId());
                Assert::assertSame($email, $event->email());
                Assert::assertSame($resetToken, $event->resetToken());
                Assert::assertInstanceOf(\DateTimeImmutable::class, $event->occurredAt());
                Assert::assertGreaterThanOrEqual($beforeDispatch->getTimestamp(), $event->occurredAt()->getTimestamp());
                Assert::assertLessThanOrEqual(new \DateTimeImmutable()->getTimestamp(), $event->occurredAt()->getTimestamp());

                return true;
            }))
            ->willReturnCallback(static fn ($message): Envelope => new Envelope($message));

        $listener = $this->listener;

        // Act
        $listener(new PasswordResetRequested(
            accountId: $accountId,
            email: $email,
            resetToken: $resetToken,
        ));

        // Assert
        $this->addToAssertionCount(1);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->listener = new SendPasswordResetEmail(
            messageBus: $this->messageBus,
        );
    }
}
