<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Bus\PasswordResetCompleted;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Infrastructure\Bus\PasswordResetCompleted\PasswordResetCompletedMessage;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(PasswordResetCompletedMessage::class)]
final class PasswordResetCompletedMessageTest extends TestCase
{
    use Factories;

    #[Test]
    public function constructs_message_with_expected_account_id_and_email(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();

        // Act
        $message = new PasswordResetCompletedMessage(
            accountId: $account->id()->asString(),
            email: $account->email(),
        );

        // Assert
        Assert::assertSame($account->id()->asString(), $message->accountId);
        Assert::assertSame($account->email(), $message->email);
    }

    #[Test]
    public function throws_error_when_trying_to_mutate_readonly_properties(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $message = new PasswordResetCompletedMessage(
            accountId: $account->id()->asString(),
            email: $account->email(),
        );

        // Act
        $thrown = null;

        try {
            $message->email = 'changed@example.com';
        } catch (\Error $error) {
            $thrown = $error;
        }

        // Assert
        Assert::assertInstanceOf(\Error::class, $thrown);
        Assert::assertStringContainsString('readonly', (string) $thrown->getMessage());
        Assert::assertSame($account->email(), $message->email);
    }
}
