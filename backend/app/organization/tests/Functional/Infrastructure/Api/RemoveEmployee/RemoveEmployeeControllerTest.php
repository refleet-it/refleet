<?php

declare(strict_types=1);

namespace App\Tests\Functional\Organization\Infrastructure\Api\RemoveEmployee;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use App\Tests\Helpers\Messenger\DrainsCrossContextQueues;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class RemoveEmployeeControllerTest extends WebTestCase
{
    use DrainsCrossContextQueues;
    use Factories;
    use ResetDatabase;

    #[Test]
    public function owner_removes_an_employee(): void
    {
        // Arrange
        $client = self::createClient();
        $owner = AccountFactory::createOne(['email' => Email::fromString('owner@example.com')]);
        $employeeAccount = AccountFactory::createOne(['email' => Email::fromString('employee@example.com')]);
        $this->drainCrossContextQueues();

        $this->authenticateAs($client, $owner);
        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme Inc.']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $client->jsonRequest('POST', '/api/organizations/employees', ['email' => 'employee@example.com']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        // Act
        $client->jsonRequest('DELETE', \sprintf('/api/organizations/employees/%s', $employeeAccount->id()->asString()));

        // Assert
        Assert::assertSame(204, $client->getResponse()->getStatusCode());

        $client->jsonRequest('GET', '/api/organizations/employees');
        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $employeeIds = \array_column($response['employees'] ?? [], 'accountId');
        Assert::assertNotContains($employeeAccount->id()->asString(), $employeeIds);
    }

    #[Test]
    public function owner_cannot_remove_themselves(): void
    {
        // Arrange
        $client = self::createClient();
        $owner = AccountFactory::createOne(['email' => Email::fromString('owner2@example.com')]);
        $this->drainCrossContextQueues();

        $this->authenticateAs($client, $owner);
        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme Inc.']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        // Act
        $client->jsonRequest('DELETE', \sprintf('/api/organizations/employees/%s', $owner->id()->asString()));

        // Assert
        Assert::assertSame(403, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function non_owner_cannot_remove_employees(): void
    {
        // Arrange
        $client = self::createClient();
        $owner = AccountFactory::createOne(['email' => Email::fromString('owner3@example.com')]);
        $employeeAccount = AccountFactory::createOne(['email' => Email::fromString('employee3@example.com')]);
        $this->drainCrossContextQueues();

        $this->authenticateAs($client, $owner);
        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme Inc.']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $client->jsonRequest('POST', '/api/organizations/employees', ['email' => 'employee3@example.com']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        // Act
        $this->authenticateAs($client, $employeeAccount);
        $client->jsonRequest('DELETE', \sprintf('/api/organizations/employees/%s', $owner->id()->asString()));

        // Assert
        Assert::assertSame(403, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function returns_not_found_for_an_employee_outside_the_organization(): void
    {
        // Arrange
        $client = self::createClient();
        $owner = AccountFactory::createOne(['email' => Email::fromString('owner4@example.com')]);
        $stranger = AccountFactory::createOne(['email' => Email::fromString('stranger@example.com')]);
        $this->drainCrossContextQueues();

        $this->authenticateAs($client, $owner);
        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme Inc.']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        // Act
        $client->jsonRequest('DELETE', \sprintf('/api/organizations/employees/%s', $stranger->id()->asString()));

        // Assert
        Assert::assertSame(404, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function returns_unauthorized_when_not_authenticated(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->jsonRequest('DELETE', \sprintf('/api/organizations/employees/%s', AccountFactory::createOne()->id()->asString()));

        // Assert
        Assert::assertSame(401, $client->getResponse()->getStatusCode());
    }

    /**
     * `loginUser()` only survives the single request made immediately after it: this app
     * runs its API firewall stateless, so the security token storage is reset before every
     * subsequent request regardless of session support. A real, self-contained JWT sent as
     * a Bearer header survives that reset the same way a real client's would.
     */
    private function authenticateAs(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, \App\Identity\Account\Domain\Account\Model\Account $account): void
    {
        $token = self::getContainer()->get(TokenGenerator::class)->generate($account);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);
    }
}
