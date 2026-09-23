<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\DomainListener\EmailVerificationEmailRequested;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Application\DomainListener\EmailVerificationEmailRequested\PublishEmailVerificationMessage;
use App\Identity\Account\Domain\Account\Event\EmailVerificationEmailRequested;
use App\Identity\Account\Infrastructure\Bus\EmailVerification\EmailVerificationMessage;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(PublishEmailVerificationMessage::class)]
final class PublishEmailVerificationMessageTest extends TestCase
{
    use Factories;

    private MessageBusInterface&MockObject $messageBus;

    private PublishEmailVerificationMessage $listener;

    #[Test]
    public function dispatches_email_verification_message_with_payload_from_domain_event(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $email = 'Mixed.Case+alias@example.com';
        $verificationToken = 'token-+/=?#value';
        $event = EmailVerificationEmailRequested::fromEmailVerificationRequested(
            accountId: $account->id(),
            email: $email,
            verificationToken: $verificationToken,
            occurredAt: new \DateTimeImmutable('2025-01-15T12:30:00+00:00'),
        );

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use ($account, $email, $verificationToken): Envelope {
                Assert::assertInstanceOf(EmailVerificationMessage::class, $message);
                Assert::assertSame($account->id()->asString(), $message->accountId);
                Assert::assertSame($email, $message->email);
                Assert::assertSame($verificationToken, $message->verificationToken);

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
        $token = $account->requestEmailVerification();
        $occurredAt = new \DateTimeImmutable('2001-02-03T04:05:06+00:00');
        $event = EmailVerificationEmailRequested::fromEmailVerificationRequested(
            accountId: $account->id(),
            email: $account->email(),
            verificationToken: $token->token(),
            occurredAt: $occurredAt,
        );

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use ($account, $token): Envelope {
                Assert::assertInstanceOf(EmailVerificationMessage::class, $message);
                Assert::assertSame($account->id()->asString(), $message->accountId);
                Assert::assertSame($account->email(), $message->email);
                Assert::assertSame($token->token(), $message->verificationToken);

                return new Envelope($message);
            });

        // Act
        ($this->listener)($event);

        // Assert
        Assert::assertSame($account->id()->asString(), $event->accountId()->asString());
        Assert::assertSame($account->email(), $event->email());
        Assert::assertSame($token->token(), $event->verificationToken());
        Assert::assertSame($occurredAt->getTimestamp(), $event->occurredAt()->getTimestamp());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->listener = new PublishEmailVerificationMessage(
            messageBus: $this->messageBus,
        );
    }
}
