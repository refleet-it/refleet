<?php

declare(strict_types=1);

namespace App\Tests\Functional\Organization\GitLabConnection\Infrastructure\Api;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Organization\GitLabConnection\Domain\Connection\Model\GitLabConnection;
use App\Organization\GitLabConnection\Domain\Connection\Repository\GitLabConnectionRepositoryInterface;
use App\Organization\GitLabConnection\Domain\Connection\Service\GitLabTokenEncryptorInterface;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\AccountId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabConnectionId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class GetGitLabCredentialsForRunnerControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function it_is_unauthorized_without_a_bearer_token(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->jsonRequest('GET', '/api/runner/gitlab-credentials', []);

        // Assert
        Assert::assertSame(401, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function it_is_unauthorized_with_a_garbage_bearer_token(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->jsonRequest('GET', '/api/runner/gitlab-credentials', [], [
            'HTTP_AUTHORIZATION' => 'Bearer sw_not-a-real-key',
        ]);

        // Assert
        Assert::assertSame(401, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function it_refuses_a_browser_session_even_for_an_organization_owner(): void
    {
        // Arrange
        $client = self::createClient();
        $account = AccountFactory::createOne();
        $jwt = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->loginAndGetJwt($client, $account->email())];

        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme'], $jwt);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        // Act
        $client->jsonRequest('GET', '/api/runner/gitlab-credentials', [], $jwt);

        // Assert
        Assert::assertSame(403, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function it_returns_conflict_when_the_account_has_no_organization(): void
    {
        // Arrange
        $client = self::createClient();
        $account = AccountFactory::createOne();
        $jwt = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->loginAndGetJwt($client, $account->email())];

        $client->jsonRequest('POST', '/api/identity/api-keys', ['name' => 'runner'], $jwt);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $apiKey = $this->decode($client->getResponse());

        // Act
        $client->jsonRequest('GET', '/api/runner/gitlab-credentials', [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$apiKey['token'],
        ]);

        // Assert
        Assert::assertSame(409, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function it_returns_not_found_when_the_organization_has_no_gitlab_connection(): void
    {
        // Arrange
        $client = self::createClient();
        $account = AccountFactory::createOne();
        $jwt = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->loginAndGetJwt($client, $account->email())];

        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme'], $jwt);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        $client->jsonRequest('POST', '/api/identity/api-keys', ['name' => 'runner'], $jwt);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $apiKey = $this->decode($client->getResponse());

        // Act
        $client->jsonRequest('GET', '/api/runner/gitlab-credentials', [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$apiKey['token'],
        ]);

        // Assert
        Assert::assertSame(404, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function it_returns_the_decrypted_gitlab_credentials_for_a_connected_organization(): void
    {
        // Arrange
        $client = self::createClient();
        $account = AccountFactory::createOne();
        $jwt = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->loginAndGetJwt($client, $account->email())];

        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme'], $jwt);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $organizationId = $this->decode($client->getResponse())['id'];

        $client->jsonRequest('POST', '/api/identity/api-keys', ['name' => 'runner'], $jwt);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $apiKey = $this->decode($client->getResponse());

        $connections = self::getContainer()->get(GitLabConnectionRepositoryInterface::class);
        $tokenEncryptor = self::getContainer()->get(GitLabTokenEncryptorInterface::class);

        $connections->save(GitLabConnection::connect(
            id: GitLabConnectionId::generate(),
            organizationId: OrganizationId::fromString($organizationId),
            baseUrl: 'https://gitlab.example.com',
            groupId: '1',
            groupPath: 'acme-robotics',
            groupName: 'Acme Robotics',
            accessTokenCiphertext: $tokenEncryptor->encrypt('glpat-super-secret'),
            connectedByAccountId: AccountId::generate(),
        ));

        // Act
        $client->jsonRequest('GET', '/api/runner/gitlab-credentials', [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$apiKey['token'],
        ]);

        // Assert
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $body = $this->decode($client->getResponse());
        Assert::assertSame('https://gitlab.example.com', $body['baseUrl']);
        Assert::assertSame('glpat-super-secret', $body['accessToken']);
    }

    private function loginAndGetJwt(KernelBrowser $client, string $email): string
    {
        $client->jsonRequest('POST', '/api/identity/login', [
            'email' => $email,
            'password' => 'password123',
        ]);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());

        /** @var array{token: array{jwtToken: string}} $response */
        $response = $this->decode($client->getResponse());

        return $response['token']['jwtToken'];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        return \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
