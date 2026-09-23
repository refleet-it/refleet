<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\DomainListener\EmailVerified;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Application\DomainListener\EmailVerified\LogEmailVerified;
use App\Identity\Account\Domain\Account\Event\EmailVerified;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(LogEmailVerified::class)]
final class LogEmailVerifiedTest extends TestCase
{
    use Factories;

    private LoggerInterface&MockObject $logger;

    private LogEmailVerified $listener;

    #[Test]
    public function logs_expected_message_and_context_when_email_is_verified(): void
    {
        // Arrange
        $account = AccountFactory::new([
            'id' => AccountId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            'email' => Email::fromString('User.Verified+1@Example.com'),
        ])->withoutPersisting()->create();

        $event = new EmailVerified(
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
        Assert::assertSame('Email verified', $loggedMessage);
        Assert::assertIsArray($loggedContext);
        Assert::assertSame([
            'accountId' => '550e8400-e29b-41d4-a716-446655440001',
            'email' => 'user.verified+1@example.com',
        ], $loggedContext);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->listener = new LogEmailVerified(
            logger: $this->logger,
        );
    }
}
