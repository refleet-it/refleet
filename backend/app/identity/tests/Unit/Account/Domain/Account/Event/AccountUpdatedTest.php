<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Event;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Domain\Account\Event\AccountUpdated;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(AccountUpdated::class)]
final class AccountUpdatedTest extends TestCase
{
    use Factories;

    #[Test]
    public function creates_event_and_exposes_payload_and_aggregate_id(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $version = 42;

        // Act
        $event = new AccountUpdated(
            accountId: $account->id(),
            email: $account->email(),
            version: $version,
        );

        // Assert
        Assert::assertTrue($event->accountId->equals($account->id()));
        Assert::assertSame($account->email(), $event->email);
        Assert::assertSame($version, $event->version);
        Assert::assertTrue($event->getAggregateId()->equals($account->id()));
    }

    #[Test]
    public function preserves_non_positive_version_values(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();

        // Act
        $zeroVersionEvent = new AccountUpdated(
            accountId: $account->id(),
            email: $account->email(),
            version: 0,
        );
        $negativeVersionEvent = new AccountUpdated(
            accountId: $account->id(),
            email: $account->email(),
            version: -1,
        );

        // Assert
        Assert::assertSame(0, $zeroVersionEvent->version);
        Assert::assertSame(-1, $negativeVersionEvent->version);
    }

    #[Test]
    public function throws_error_when_trying_to_mutate_readonly_property(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $event = new AccountUpdated(
            accountId: $account->id(),
            email: $account->email(),
            version: 1,
        );

        // Act
        $thrown = null;

        try {
            $event->version = 2;
        } catch (\Error $error) {
            $thrown = $error;
        }

        // Assert
        Assert::assertInstanceOf(\Error::class, $thrown);
        Assert::assertStringContainsString('readonly', (string) $thrown->getMessage());
        Assert::assertSame(1, $event->version);
    }

    #[Test]
    public function throws_type_error_for_non_int_version(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $invalidVersion = \json_decode('1.5', true);
        $thrown = null;

        // Act
        try {
            new AccountUpdated(
                accountId: $account->id(),
                email: $account->email(),
                version: $invalidVersion,
            );
        } catch (\TypeError $typeError) {
            $thrown = $typeError;
        }

        // Assert
        Assert::assertInstanceOf(\TypeError::class, $thrown);
        Assert::assertStringContainsString('int', (string) $thrown->getMessage());
    }
}
