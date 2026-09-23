<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Http\Auth\Register;

use App\Identity\Account\Domain\Account\Enum\AccountStatusEnum;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class RegisterControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function registers_account_with_user_role_pending_verification(): void
    {
        // Arrange
        $client = self::createClient();
        $email = 'new.user.'.\bin2hex(\random_bytes(6)).'@example.com';

        // Act
        $client->jsonRequest('POST', '/api/identity/register', [
            'email' => $email,
            'password' => 'StrongPassword123',
            'termsAccepted' => true,
        ]);

        // Assert
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        $account = self::getContainer()->get(AccountRepositoryInterface::class)->findByEmail($email);
        Assert::assertNotNull($account);
        Assert::assertSame(RoleEnum::USER, $account->role());
        Assert::assertSame(AccountStatusEnum::PENDING_EMAIL_VERIFICATION, $account->status());
    }

    #[Test]
    public function ignores_role_and_skip_email_verification_fields_injected_in_raw_payload(): void
    {
        // Arrange: an attacker sending fields that RegisterRequest no longer exposes
        $client = self::createClient();
        $email = 'attacker.'.\bin2hex(\random_bytes(6)).'@example.com';

        // Act
        $client->jsonRequest('POST', '/api/identity/register', [
            'email' => $email,
            'password' => 'StrongPassword123',
            'termsAccepted' => true,
            'role' => 'administrator',
            'skipEmailVerification' => true,
        ]);

        // Assert
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        $account = self::getContainer()->get(AccountRepositoryInterface::class)->findByEmail($email);
        Assert::assertNotNull($account);
        Assert::assertSame(RoleEnum::USER, $account->role());
        Assert::assertSame(AccountStatusEnum::PENDING_EMAIL_VERIFICATION, $account->status());
    }
}
