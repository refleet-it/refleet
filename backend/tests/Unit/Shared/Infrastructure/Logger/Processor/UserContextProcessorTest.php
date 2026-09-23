<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Logger\Processor;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Infrastructure\Factory\AccountUserFactory;
use App\Shared\Infrastructure\Logger\Processor\UserContextProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(UserContextProcessor::class)]
final class UserContextProcessorTest extends TestCase
{
    use Factories;

    #[Test]
    public function returns_same_record_when_authenticated_user_is_not_account_user(): void
    {
        // Arrange
        $record = $this->createRecord(['trace_id' => 'abc123']);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('getUser')->willReturn(null);

        $processor = new UserContextProcessor($security);

        // Act
        $processed = $processor($record);

        // Assert
        Assert::assertSame($record, $processed);
        Assert::assertSame(['trace_id' => 'abc123'], $processed->context);
    }

    #[Test]
    public function appends_authenticated_account_user_context_to_record(): void
    {
        // Arrange
        $account = AccountFactory::new([
            'role' => RoleEnum::USER,
        ])->withoutPersisting()->create();
        $user = new AccountUserFactory()->createFromAccount($account);
        $record = $this->createRecord(['trace_id' => 'abc123']);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('getUser')->willReturn($user);

        $processor = new UserContextProcessor($security);

        // Act
        $processed = $processor($record);

        // Assert
        Assert::assertNotSame($record, $processed);
        Assert::assertSame([
            'trace_id' => 'abc123',
            'user_id' => $account->id()->asString(),
            'email' => $account->email(),
            'roles' => [$account->role()->toSymfonyRole()],
        ], $processed->context);
        Assert::assertSame(['trace_id' => 'abc123'], $record->context);
    }

    #[Test]
    public function overrides_conflicting_user_context_keys_with_authenticated_account_user_values(): void
    {
        // Arrange
        $account = AccountFactory::new([
            'role' => RoleEnum::USER,
        ])->withoutPersisting()->create();
        $user = new AccountUserFactory()->createFromAccount($account);
        $record = $this->createRecord([
            'user_id' => 'legacy-user-id',
            'email' => 'legacy@example.com',
            'roles' => ['ROLE_LEGACY'],
            'request_id' => 'req-1',
        ]);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('getUser')->willReturn($user);

        $processor = new UserContextProcessor($security);

        // Act
        $processed = $processor($record);

        // Assert
        Assert::assertSame([
            'user_id' => $account->id()->asString(),
            'email' => $account->email(),
            'roles' => [$account->role()->toSymfonyRole()],
            'request_id' => 'req-1',
        ], $processed->context);
    }

    private function createRecord(array $context): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: 'test message',
            context: $context,
        );
    }
}
