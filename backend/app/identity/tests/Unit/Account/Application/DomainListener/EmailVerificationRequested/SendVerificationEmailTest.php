<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\DomainListener\EmailVerificationRequested;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Application\DomainListener\EmailVerificationRequested\SendVerificationEmail;
use App\Identity\Account\Domain\Account\Event\EmailVerificationEmailRequested;
use App\Identity\Account\Domain\Account\Event\EmailVerificationRequested;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(SendVerificationEmail::class)]
final class SendVerificationEmailTest extends TestCase
{
    use Factories;

    private MessageBusInterface&MockObject $messageBus;

    private SendVerificationEmail $listener;

    #[Test]
    public function dispatches_email_verification_email_requested_with_payload_from_event(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $verificationToken = 'verification-token-123';
        $beforeDispatch = new \DateTimeImmutable();

        $event = new EmailVerificationRequested(
            accountId: $account->id(),
            email: 'Mixed.Case+alias@example.com',
            verificationToken: $verificationToken,
        );

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $dispatchedEvent) use ($account, $verificationToken, $beforeDispatch): bool {
                Assert::assertInstanceOf(EmailVerificationEmailRequested::class, $dispatchedEvent);
                Assert::assertSame($account->id()->asString(), $dispatchedEvent->accountId()->asString());
                Assert::assertSame('Mixed.Case+alias@example.com', $dispatchedEvent->email());
                Assert::assertSame($verificationToken, $dispatchedEvent->verificationToken());
                Assert::assertGreaterThanOrEqual($beforeDispatch->getTimestamp(), $dispatchedEvent->occurredAt()->getTimestamp());
                Assert::assertLessThanOrEqual(new \DateTimeImmutable()->getTimestamp(), $dispatchedEvent->occurredAt()->getTimestamp());

                return true;
            }))
            ->willReturnCallback(static fn ($message): Envelope => new Envelope($message));

        // Act
        ($this->listener)($event);

        // Assert
        Assert::assertSame($account->id()->asString(), $event->accountId()->asString());
    }

    #[Test]
    public function dispatches_event_created_from_account_email_verification_request(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $token = $account->requestEmailVerification();
        $beforeDispatch = new \DateTimeImmutable();
        $events = $account->getRecordedDomainEvents();
        $event = \array_first(\array_filter(
            $events,
            static fn (object $event): bool => $event instanceof EmailVerificationRequested,
        )) ?? null;

        Assert::assertInstanceOf(EmailVerificationRequested::class, $event);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $dispatchedEvent) use ($account, $token, $beforeDispatch): bool {
                Assert::assertInstanceOf(EmailVerificationEmailRequested::class, $dispatchedEvent);
                Assert::assertSame($account->id()->asString(), $dispatchedEvent->accountId()->asString());
                Assert::assertSame($account->email(), $dispatchedEvent->email());
                Assert::assertSame($token->token(), $dispatchedEvent->verificationToken());
                Assert::assertGreaterThanOrEqual($beforeDispatch->getTimestamp(), $dispatchedEvent->occurredAt()->getTimestamp());
                Assert::assertLessThanOrEqual(new \DateTimeImmutable()->getTimestamp(), $dispatchedEvent->occurredAt()->getTimestamp());

                return true;
            }))
            ->willReturnCallback(static fn ($message): Envelope => new Envelope($message));

        // Act
        ($this->listener)($event);

        // Assert
        Assert::assertSame($token->token(), $event->verificationToken());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->listener = new SendVerificationEmail(
            messageBus: $this->messageBus,
        );
    }
}
