<?php

declare(strict_types=1);

namespace App\Tests\Functional\Project\Infrastructure\Api;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ProjectFlowTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function account_without_an_organization_cannot_register_or_list_projects(): void
    {
        // Arrange
        $client = self::createClient();
        $account = AccountFactory::createOne();
        $this->authenticateAs($client, $account);

        // Act
        $client->jsonRequest('POST', '/api/projects', [
            'name' => 'Payments Service',
            'externalId' => '1',
            'path' => 'team/payments-service',
        ]);

        // Assert
        Assert::assertSame(409, $client->getResponse()->getStatusCode());

        // Act
        $client->jsonRequest('GET', '/api/projects');

        // Assert
        Assert::assertSame(409, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function registers_lists_and_re_syncs_a_project(): void
    {
        // Arrange
        $client = self::createClient();
        $account = AccountFactory::createOne();
        $this->authenticateAs($client, $account);
        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme Inc.']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        // Act: register a new project
        $client->jsonRequest('POST', '/api/projects', [
            'name' => 'Payments Service',
            'externalId' => '48210942',
            'path' => 'backend-team/payments-service',
            'webUrl' => 'https://gitlab.com/backend-team/payments-service',
            'defaultBranch' => 'main',
        ]);

        // Assert
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $registered = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('Payments Service', $registered['name'] ?? null);
        Assert::assertSame('48210942', $registered['externalId'] ?? null);

        // Act: list projects
        $client->jsonRequest('GET', '/api/projects');
        $list = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        // Assert
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        Assert::assertCount(1, $list['projects'] ?? []);
        Assert::assertSame('Payments Service', $list['projects'][0]['name'] ?? null);

        // Act: re-register the same externalId with updated data
        $client->jsonRequest('POST', '/api/projects', [
            'name' => 'Payments Service Renamed',
            'externalId' => '48210942',
            'path' => 'backend-team/payments-service',
            'defaultBranch' => 'develop',
        ]);

        // Assert: updated in place, not duplicated
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $client->jsonRequest('GET', '/api/projects');
        $listAfter = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertCount(1, $listAfter['projects'] ?? []);
        Assert::assertSame('Payments Service Renamed', $listAfter['projects'][0]['name'] ?? null);
        Assert::assertSame('develop', $listAfter['projects'][0]['defaultBranch'] ?? null);
    }

    /**
     * `loginUser()` only survives the single request made immediately after it: this app
     * runs its API firewall stateless, so the security token storage is reset before every
     * subsequent request regardless of session support. A real, self-contained JWT sent as
     * a Bearer header survives that reset the same way a real client's would.
     */
    private function authenticateAs(KernelBrowser $client, Account $account): void
    {
        $token = self::getContainer()->get(TokenGenerator::class)->generate($account);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);
    }
}
