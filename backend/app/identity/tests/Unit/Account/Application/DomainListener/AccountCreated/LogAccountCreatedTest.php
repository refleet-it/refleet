<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\DomainListener\AccountCreated;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Application\DomainListener\AccountCreated\LogAccountCreated;
use App\Identity\Account\Domain\Account\Event\AccountCreated;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(LogAccountCreated::class)]
final class LogAccountCreatedTest extends TestCase
{
    use Factories;

    private LoggerInterface&MockObject $logger;

    private LogAccountCreated $listener;

    #[Test]
    public function logs_expected_message_and_context_when_account_is_created(): void
    {
        // Arrange
        $account = AccountFactory::new([
            'id' => AccountId::fromString('550e8400-e29b-41d4-a716-446655440101'),
            'email' => Email::fromString('User.Created+1@Example.com'),
        ])->withoutPersisting()->create();

        $event = new AccountCreated(
            accountId: $account->id(),
            email: $account->email(),
        );

        $loggedMessage = null;
        $loggedContext = null;

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->willReturnCallback(static function (string $message, array $context) use (&$loggedMessage, &$loggedContext): void {
                $loggedMessage = $message;
                $loggedContext = $context;
            });

        // Act
        ($this->listener)($event);

        // Assert
        Assert::assertSame('Account created', $loggedMessage);
        Assert::assertIsArray($loggedContext);
        Assert::assertSame([
            'accountId' => '550e8400-e29b-41d4-a716-446655440101',
            'email' => 'user.created+1@example.com',
        ], $loggedContext);
    }

    #[Test]
    public function logs_email_value_exactly_as_provided_by_event_payload(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $customEmail = 'MIXED.Case+Tag@Example.COM';

        $event = new AccountCreated(
            accountId: $account->id(),
            email: $customEmail,
        );

        $loggedMessage = null;
        $loggedContext = null;

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->willReturnCallback(static function (string $message, array $context) use (&$loggedMessage, &$loggedContext): void {
                $loggedMessage = $message;
                $loggedContext = $context;
            });

        // Act
        ($this->listener)($event);

        // Assert
        Assert::assertSame('Account created', $loggedMessage);
        Assert::assertIsArray($loggedContext);
        Assert::assertSame($account->id()->asString(), $loggedContext['accountId'] ?? null);
        Assert::assertSame($customEmail, $loggedContext['email'] ?? null);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->listener = new LogAccountCreated(
            logger: $this->logger,
        );
    }
}
