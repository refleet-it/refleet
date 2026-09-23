<?php

declare(strict_types=1);

namespace App\Tests\Functional\Organization\Infrastructure\Api;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use App\Tests\Helpers\Messenger\DrainsCrossContextQueues;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class OrganizationFlowTest extends WebTestCase
{
    use DrainsCrossContextQueues;
    use Factories;
    use ResetDatabase;

    #[Test]
    public function account_without_organization_sees_a_null_organization(): void
    {
        // Arrange
        $client = self::createClient();
        $account = AccountFactory::createOne();
        $this->drainCrossContextQueues();
        $this->authenticateAs($client, $account);

        // Act
        $client->jsonRequest('GET', '/api/organizations/me');

        // Assert
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertArrayHasKey('organization', $response);
        Assert::assertNull($response['organization']);
    }

    #[Test]
    public function full_lifecycle_create_organization_view_it_and_add_an_employee(): void
    {
        // Arrange
        $client = self::createClient();
        $owner = AccountFactory::createOne(['email' => Email::fromString('owner@example.com')]);
        $employeeAccount = AccountFactory::createOne(['email' => Email::fromString('employee@example.com')]);
        $this->drainCrossContextQueues();

        // Act: create organization
        $this->authenticateAs($client, $owner);
        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme Inc.']);

        // Assert
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $created = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('Acme Inc.', $created['name'] ?? null);
        Assert::assertSame('owner', $created['role'] ?? null);

        // Act: creating a second organization for the same account must fail
        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Second Org']);
        Assert::assertSame(409, $client->getResponse()->getStatusCode());

        // Act: fetch overview - just the owner so far
        $client->jsonRequest('GET', '/api/organizations/me');
        $overview = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('Acme Inc.', $overview['organization']['name'] ?? null);
        Assert::assertSame('owner', $overview['role'] ?? null);

        $client->jsonRequest('GET', '/api/organizations/employees');
        $employees = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertCount(1, $employees['employees'] ?? []);

        // Act: add the second account as an employee
        $client->jsonRequest('POST', '/api/organizations/employees', ['email' => 'employee@example.com']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $added = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('employee@example.com', $added['email'] ?? null);
        Assert::assertSame('user', $added['role'] ?? null);

        // Act: employee list now has both employees
        $client->jsonRequest('GET', '/api/organizations/employees');
        $employeesAfter = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertCount(2, $employeesAfter['employees'] ?? []);

        // Act: the added employee (not an owner) cannot add further employees
        $this->authenticateAs($client, $employeeAccount);
        AccountFactory::createOne(['email' => Email::fromString('third@example.com')]);
        $client->jsonRequest('POST', '/api/organizations/employees', ['email' => 'third@example.com']);
        Assert::assertSame(403, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function adding_an_employee_with_an_unregistered_email_returns_not_found(): void
    {
        // Arrange
        $client = self::createClient();
        $owner = AccountFactory::createOne();
        $this->drainCrossContextQueues();
        $this->authenticateAs($client, $owner);
        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme Inc.']);

        // Act
        $client->jsonRequest('POST', '/api/organizations/employees', ['email' => 'nobody@example.com']);

        // Assert
        Assert::assertSame(404, $client->getResponse()->getStatusCode());
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
