<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Http\Auth\Refresh;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Infrastructure\Factory\RefreshTokenCookieFactory;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class RefreshControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function rotates_the_refresh_token_cookie_on_success(): void
    {
        // Arrange
        $client = self::createClient();
        $account = AccountFactory::createOne();

        $client->jsonRequest('POST', '/api/identity/login', [
            'email' => $account->email(),
            'password' => 'password123',
        ]);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());

        $oldCookie = $client->getCookieJar()->get(
            RefreshTokenCookieFactory::COOKIE_NAME,
            '/api/identity'
        );
        Assert::assertNotNull($oldCookie);

        // Act
        $client->jsonRequest('POST', '/api/identity/refresh');

        // Assert: refresh succeeds and a NEW, different token is issued
        Assert::assertSame(200, $client->getResponse()->getStatusCode());

        $newCookie = $client->getCookieJar()->get(
            RefreshTokenCookieFactory::COOKIE_NAME,
            '/api/identity'
        );
        Assert::assertNotNull($newCookie);
        Assert::assertNotSame($oldCookie->getValue(), $newCookie->getValue());
    }

    #[Test]
    public function rejects_reuse_of_an_already_rotated_refresh_token(): void
    {
        // Arrange
        $client = self::createClient();
        $account = AccountFactory::createOne();

        $client->jsonRequest('POST', '/api/identity/login', [
            'email' => $account->email(),
            'password' => 'password123',
        ]);
        $originalCookie = $client->getCookieJar()->get(
            RefreshTokenCookieFactory::COOKIE_NAME,
            '/api/identity'
        );
        Assert::assertNotNull($originalCookie);
        $originalValue = $originalCookie->getValue();

        // First refresh rotates (and revokes) the original token
        $client->jsonRequest('POST', '/api/identity/refresh');
        Assert::assertSame(200, $client->getResponse()->getStatusCode());

        // Act: replay the original, now-revoked token
        $client->getCookieJar()->set(new Cookie(
            RefreshTokenCookieFactory::COOKIE_NAME,
            $originalValue,
            null,
            '/api/identity'
        ));
        $client->jsonRequest('POST', '/api/identity/refresh');

        // Assert
        Assert::assertSame(422, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function returns_401_when_no_refresh_token_cookie_is_present(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->jsonRequest('POST', '/api/identity/refresh');

        // Assert
        Assert::assertSame(401, $client->getResponse()->getStatusCode());
    }
}
