<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Http\CliAuthorization;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Domain\Account\Model\Account;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class CliAuthorizationFlowTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function the_cli_collects_an_api_key_once_the_browser_approves(): void
    {
        // Arrange: the CLI starts a login while the browser has an account to sign in with
        $client = self::createClient();
        $account = AccountFactory::createOne();
        $started = $this->start($client);

        Assert::assertStringEndsWith('/cli/authorize/'.$started['userCode'], $started['verificationUrl']);
        Assert::assertSame(3, $started['pollIntervalSeconds']);

        // A poll before anyone approved is just "pending"
        $client->jsonRequest('POST', '/api/identity/cli-authorizations/claim', $this->claimBody($started));
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        Assert::assertSame('pending', $this->json($client)['status']);

        $jwt = $this->jwtFor($client, $account);
        $client->request('GET', '/api/identity/cli-authorizations/'.$started['userCode'], server: $this->bearer($jwt));
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $shown = $this->json($client);
        Assert::assertSame('my-laptop', $shown['runnerName']);
        Assert::assertSame('pending', $shown['status']);
        Assert::assertArrayNotHasKey('deviceSecret', $shown);

        // Act: approve, then the CLI polls again
        $client->request('POST', '/api/identity/cli-authorizations/'.$started['userCode'].'/approve', server: $this->bearer($jwt));
        Assert::assertSame(204, $client->getResponse()->getStatusCode());

        $client->jsonRequest('POST', '/api/identity/cli-authorizations/claim', $this->claimBody($started));

        // Assert
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $claimed = $this->json($client);
        Assert::assertSame('approved', $claimed['status']);
        Assert::assertSame($account->email(), $claimed['accountEmail']);
        Assert::assertSame('refleet-runner (my-laptop)', $claimed['apiKey']['name']);
        Assert::assertStringStartsWith('ib_', $claimed['apiKey']['token']);

        // The key works, and the authorization is gone
        $client->request('GET', '/api/identity/api-keys', server: $this->bearer($claimed['apiKey']['token']));
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        Assert::assertSame(1, $this->json($client)['count']);

        $client->jsonRequest('POST', '/api/identity/cli-authorizations/claim', $this->claimBody($started));
        Assert::assertSame(404, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function a_denied_login_is_reported_to_the_cli_once_and_cannot_be_approved_afterwards(): void
    {
        $client = self::createClient();
        $account = AccountFactory::createOne();
        $started = $this->start($client);

        $jwt = $this->jwtFor($client, $account);

        $client->request('POST', '/api/identity/cli-authorizations/'.$started['userCode'].'/deny', server: $this->bearer($jwt));
        Assert::assertSame(204, $client->getResponse()->getStatusCode());

        $client->request('POST', '/api/identity/cli-authorizations/'.$started['userCode'].'/approve', server: $this->bearer($jwt));
        Assert::assertSame(409, $client->getResponse()->getStatusCode());

        $client->jsonRequest('POST', '/api/identity/cli-authorizations/claim', $this->claimBody($started));
        Assert::assertSame('denied', $this->json($client)['status']);

        $client->jsonRequest('POST', '/api/identity/cli-authorizations/claim', $this->claimBody($started));
        Assert::assertSame(404, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function starting_and_polling_need_no_credentials_but_looking_and_deciding_do(): void
    {
        $client = self::createClient();
        $started = $this->start($client);

        $client->jsonRequest('POST', '/api/identity/cli-authorizations/claim', $this->claimBody($started));
        Assert::assertSame(200, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/identity/cli-authorizations/'.$started['userCode']);
        Assert::assertSame(401, $client->getResponse()->getStatusCode());

        $client->request('POST', '/api/identity/cli-authorizations/'.$started['userCode'].'/approve');
        Assert::assertSame(401, $client->getResponse()->getStatusCode());

        $client->request('POST', '/api/identity/cli-authorizations/'.$started['userCode'].'/deny');
        Assert::assertSame(401, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function an_unknown_code_is_not_found_and_a_wrong_secret_never_yields_a_key(): void
    {
        $client = self::createClient();

        $jwt = $this->jwtFor($client, AccountFactory::createOne());

        $client->request('GET', '/api/identity/cli-authorizations/'.\str_repeat('0', 24), server: $this->bearer($jwt));
        Assert::assertSame(404, $client->getResponse()->getStatusCode());

        $client->jsonRequest('POST', '/api/identity/cli-authorizations/claim', ['deviceSecret' => 'wrong', 'apiKeyName' => 'x']);
        Assert::assertSame(404, $client->getResponse()->getStatusCode());
    }

    private function jwtFor(KernelBrowser $client, Account $account): string
    {
        $client->jsonRequest('POST', '/api/identity/login', ['email' => $account->email(), 'password' => 'password123']);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());

        /** @var array{token: array{jwtToken: string}} $body */
        $body = $this->json($client);

        return $body['token']['jwtToken'];
    }

    /** @return array{HTTP_AUTHORIZATION: string} */
    private function bearer(string $token): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$token];
    }

    /** @return array{userCode: string, deviceSecret: string, verificationUrl: string, pollIntervalSeconds: int} */
    private function start(KernelBrowser $client): array
    {
        $client->jsonRequest('POST', '/api/identity/cli-authorizations', ['runnerName' => 'my-laptop']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        /** @var array{userCode: string, deviceSecret: string, verificationUrl: string, pollIntervalSeconds: int} $body */
        $body = $this->json($client);

        return $body;
    }

    /** @param array{deviceSecret: string} $started */
    private function claimBody(array $started): array
    {
        return ['deviceSecret' => $started['deviceSecret'], 'apiKeyName' => 'refleet-runner (my-laptop)'];
    }

    /** @return array<string, mixed> */
    private function json(KernelBrowser $client): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
