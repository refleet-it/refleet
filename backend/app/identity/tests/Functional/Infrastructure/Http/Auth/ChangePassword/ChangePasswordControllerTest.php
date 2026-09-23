<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Http\Auth\ChangePassword;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Infrastructure\Factory\AccountUserFactory;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ChangePasswordControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function changes_password_when_current_password_is_correct(): void
    {
        // Arrange
        $client = self::createClient();
        $account = AccountFactory::createOne();
        $client->loginUser((new AccountUserFactory())->createFromAccount($account));

        // Act
        $client->jsonRequest('POST', '/api/identity/change-password', [
            'currentPassword' => 'password123',
            'newPassword' => 'BrandNewPassword1!',
        ]);

        // Assert
        Assert::assertSame(200, $client->getResponse()->getStatusCode());

        // The new password now works for login
        $client->jsonRequest('POST', '/api/identity/login', [
            'email' => $account->email(),
            'password' => 'BrandNewPassword1!',
        ]);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function rejects_change_when_current_password_is_incorrect(): void
    {
        // Arrange
        $client = self::createClient();
        $account = AccountFactory::createOne();
        $client->loginUser((new AccountUserFactory())->createFromAccount($account));

        // Act
        $client->jsonRequest('POST', '/api/identity/change-password', [
            'currentPassword' => 'totally-wrong-password',
            'newPassword' => 'BrandNewPassword1!',
        ]);

        // Assert
        Assert::assertSame(422, $client->getResponse()->getStatusCode());
        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('INVALID_CURRENT_PASSWORD', $response['error'] ?? null);
    }

    #[Test]
    public function requires_authentication(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->jsonRequest('POST', '/api/identity/change-password', [
            'currentPassword' => 'password123',
            'newPassword' => 'BrandNewPassword1!',
        ]);

        // Assert
        Assert::assertSame(401, $client->getResponse()->getStatusCode());
    }
}
