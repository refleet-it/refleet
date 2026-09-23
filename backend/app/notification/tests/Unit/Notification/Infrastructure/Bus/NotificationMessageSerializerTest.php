<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Notification\Infrastructure\Bus;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Fixtures\Factory\Identity\EmailVerificationTokenFactory;
use App\Fixtures\Factory\Identity\PasswordResetTokenFactory;
use App\Notification\Notification\Infrastructure\Bus\NotificationMessageSerializer;
use App\Notification\Notification\Infrastructure\Bus\PasswordReset\PasswordResetMessage;
use App\Notification\Notification\Infrastructure\Bus\PasswordResetCompleted\PasswordResetCompletedMessage;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\AckStamp;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(NotificationMessageSerializer::class)]
final class NotificationMessageSerializerTest extends TestCase
{
    use Factories;

    #[Test]
    public function encode_serializes_notification_payload_type_and_stamps(): void
    {
        // Arrange
        $serializer = new NotificationMessageSerializer();
        $token = PasswordResetTokenFactory::new()->withoutPersisting()->create();
        $message = new PasswordResetMessage(
            accountId: $token->account()->id()->asString(),
            email: $token->account()->email(),
            resetToken: $token->token(),
        );
        $envelope = new Envelope($message, [new BusNameStamp('notification.bus'), new DelayStamp(1500)]);

        // Act
        $encoded = $serializer->encode($envelope);
        $decodedBody = \json_decode($encoded['body'], true, 512, \JSON_THROW_ON_ERROR);

        // Assert
        Assert::assertSame([], $encoded['headers']);
        Assert::assertSame('password_reset', $decodedBody['type']);
        Assert::assertSame($token->account()->id()->asString(), $decodedBody['data']['accountId']);
        Assert::assertSame($token->account()->email(), $decodedBody['data']['email']);
        Assert::assertSame($token->token(), $decodedBody['data']['resetToken']);
        Assert::assertArrayHasKey(BusNameStamp::class, $encoded['stamps']);
        Assert::assertArrayHasKey(DelayStamp::class, $encoded['stamps']);
        Assert::assertCount(1, $encoded['stamps'][BusNameStamp::class]);
        Assert::assertCount(1, $encoded['stamps'][DelayStamp::class]);
    }

    #[Test]
    public function encode_drops_the_transient_stamps_a_worker_adds_before_a_retry(): void
    {
        // Arrange
        $serializer = new NotificationMessageSerializer();
        $token = PasswordResetTokenFactory::new()->withoutPersisting()->create();
        $message = new PasswordResetMessage(
            accountId: $token->account()->id()->asString(),
            email: $token->account()->email(),
            resetToken: $token->token(),
        );
        $envelope = new Envelope($message, [
            new RedeliveryStamp(1),
            new AckStamp(static function (): void {}),
        ]);

        // Act
        $encoded = $serializer->encode($envelope);
        $decoded = $serializer->decode($encoded);

        // Assert
        Assert::assertArrayHasKey(RedeliveryStamp::class, $encoded['stamps']);
        Assert::assertArrayNotHasKey(AckStamp::class, $encoded['stamps']);
        Assert::assertCount(0, $decoded->all(AckStamp::class));
        $redelivery = $decoded->last(RedeliveryStamp::class);
        Assert::assertInstanceOf(RedeliveryStamp::class, $redelivery);
        Assert::assertSame(1, $redelivery->getRetryCount());
        Assert::assertInstanceOf(\DateTimeInterface::class, $redelivery->getRedeliveredAt());
    }

    #[Test]
    public function decode_restores_message_and_ignores_non_whitelisted_stamps(): void
    {
        // Arrange
        $serializer = new NotificationMessageSerializer();
        $account = AccountFactory::new()->withoutPersisting()->create();
        $encodedEnvelope = [
            'body' => \json_encode([
                'type' => 'password_reset_completed',
                'data' => [
                    'accountId' => $account->id()->asString(),
                    'email' => $account->email(),
                ],
            ], \JSON_THROW_ON_ERROR),
            'stamps' => [
                BusNameStamp::class => [\serialize(new BusNameStamp('notification.bus'))],
                DelayStamp::class => [\serialize(new DelayStamp(500))],
                'malicious' => [\serialize(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'))],
            ],
        ];

        // Act
        $envelope = $serializer->decode($encodedEnvelope);
        $message = $envelope->getMessage();
        $busNameStamps = $envelope->all(BusNameStamp::class);
        $delayStamps = $envelope->all(DelayStamp::class);
        $allStamps = \array_merge(...\array_values($envelope->all()));

        // Assert
        Assert::assertInstanceOf(PasswordResetCompletedMessage::class, $message);
        Assert::assertSame($account->id()->asString(), $message->accountId);
        Assert::assertSame($account->email(), $message->email);
        Assert::assertCount(1, $busNameStamps);
        Assert::assertCount(1, $delayStamps);
        Assert::assertCount(2, $allStamps);
    }

    #[Test]
    public function decode_throws_when_body_is_not_valid_json(): void
    {
        // Arrange
        $serializer = new NotificationMessageSerializer();
        $encodedEnvelope = ['body' => 'not-json'];

        // Act
        try {
            $serializer->decode($encodedEnvelope);
            Assert::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $runtimeException) {
            // Assert
            Assert::assertSame('Invalid encoded envelope', $runtimeException->getMessage());
        }
    }

    #[Test]
    public function decode_throws_when_message_type_is_missing(): void
    {
        // Arrange
        $serializer = new NotificationMessageSerializer();
        $token = EmailVerificationTokenFactory::new()->withoutPersisting()->create();
        $encodedEnvelope = [
            'body' => \json_encode([
                'data' => [
                    'accountId' => $token->account()->id()->asString(),
                    'email' => $token->account()->email(),
                    'verificationToken' => $token->token(),
                ],
            ], \JSON_THROW_ON_ERROR),
        ];

        // Act
        try {
            $serializer->decode($encodedEnvelope);
            Assert::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $runtimeException) {
            // Assert
            Assert::assertSame('Message type not found in encoded envelope', $runtimeException->getMessage());
        }
    }

    #[Test]
    public function decode_throws_when_message_type_is_unknown(): void
    {
        // Arrange
        $serializer = new NotificationMessageSerializer();
        $encodedEnvelope = [
            'body' => \json_encode([
                'type' => 'unknown_notification',
                'data' => ['foo' => 'bar'],
            ], \JSON_THROW_ON_ERROR),
        ];

        // Act
        try {
            $serializer->decode($encodedEnvelope);
            Assert::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $runtimeException) {
            // Assert
            Assert::assertSame('Unknown message type: unknown_notification', $runtimeException->getMessage());
        }
    }

    #[Test]
    public function decode_throws_when_message_data_is_missing_or_not_an_array(): void
    {
        // Arrange
        $serializer = new NotificationMessageSerializer();
        $encodedEnvelope = [
            'body' => \json_encode(['type' => 'email_verification', 'data' => 'invalid'], \JSON_THROW_ON_ERROR),
        ];

        // Act
        try {
            $serializer->decode($encodedEnvelope);
            Assert::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $runtimeException) {
            // Assert
            Assert::assertSame('Message data not found in encoded envelope', $runtimeException->getMessage());
        }
    }

    #[Test]
    public function decode_lets_the_message_constructor_reject_a_wrongly_typed_value(): void
    {
        // Arrange
        $serializer = new NotificationMessageSerializer();
        $encodedEnvelope = [
            'body' => \json_encode([
                'type' => 'password_reset',
                'data' => [
                    'accountId' => '11111111-1111-1111-1111-111111111111',
                    'email' => 'user@example.com',
                    'resetToken' => 123,
                ],
            ], \JSON_THROW_ON_ERROR),
        ];

        // Assert
        $this->expectException(\TypeError::class);

        // Act
        $serializer->decode($encodedEnvelope);
    }

    #[Test]
    public function decode_throws_type_error_when_null_reaches_non_nullable_message_constructor(): void
    {
        // Arrange
        $serializer = new NotificationMessageSerializer();
        $account = AccountFactory::new()->withoutPersisting()->create();
        $encodedEnvelope = [
            'body' => \json_encode([
                'type' => 'password_reset_completed',
                'data' => [
                    'accountId' => $account->id()->asString(),
                    'email' => null,
                ],
            ], \JSON_THROW_ON_ERROR),
        ];

        // Act
        try {
            $serializer->decode($encodedEnvelope);
            Assert::fail('Expected TypeError was not thrown');
        } catch (\TypeError $typeError) {
            // Assert
            Assert::assertStringContainsString(PasswordResetCompletedMessage::class, $typeError->getMessage());
        }
    }
}
