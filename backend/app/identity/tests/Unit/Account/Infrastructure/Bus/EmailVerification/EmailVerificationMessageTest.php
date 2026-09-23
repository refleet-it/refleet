<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Bus\EmailVerification;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Infrastructure\Bus\EmailVerification\EmailVerificationMessage;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(EmailVerificationMessage::class)]
final class EmailVerificationMessageTest extends TestCase
{
    use Factories;

    #[Test]
    public function constructs_message_with_expected_account_id_email_and_verification_token(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $verificationToken = 'verification-token-123';

        // Act
        $message = new EmailVerificationMessage(
            accountId: $account->id()->asString(),
            email: $account->email(),
            verificationToken: $verificationToken,
        );

        // Assert
        Assert::assertSame($account->id()->asString(), $message->accountId);
        Assert::assertSame($account->email(), $message->email);
        Assert::assertSame($verificationToken, $message->verificationToken);
    }

    #[Test]
    public function preserves_exact_payload_values_including_spacing_and_symbols(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $email = ' User+alias@Example.COM ';
        $verificationToken = " token +/?=%2B#\t ";

        // Act
        $message = new EmailVerificationMessage(
            accountId: $account->id()->asString(),
            email: $email,
            verificationToken: $verificationToken,
        );

        // Assert
        Assert::assertSame($email, $message->email);
        Assert::assertSame($verificationToken, $message->verificationToken);
    }

    #[Test]
    public function throws_error_when_trying_to_mutate_readonly_properties(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $message = new EmailVerificationMessage(
            accountId: $account->id()->asString(),
            email: $account->email(),
            verificationToken: 'token',
        );

        // Act
        $thrown = null;

        try {
            $message->verificationToken = 'changed-token';
        } catch (\Error $error) {
            $thrown = $error;
        }

        // Assert
        Assert::assertInstanceOf(\Error::class, $thrown);
        Assert::assertStringContainsString('readonly', (string) $thrown->getMessage());
        Assert::assertSame('token', $message->verificationToken);
    }

    #[Test]
    public function throws_type_error_for_non_string_account_id(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $invalidAccountId = \json_decode('123', true);
        $thrown = null;

        // Act
        try {
            new EmailVerificationMessage(
                accountId: $invalidAccountId,
                email: $account->email(),
                verificationToken: 'token',
            );
        } catch (\TypeError $typeError) {
            $thrown = $typeError;
        }

        // Assert
        Assert::assertInstanceOf(\TypeError::class, $thrown);
        Assert::assertStringContainsString('string', (string) $thrown->getMessage());
    }
}
