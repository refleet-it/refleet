<?php

declare(strict_types=1);

namespace App\Tests\Functional\Organization\Infrastructure\Api\SendInvitation;

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

final class SendInvitationControllerTest extends WebTestCase
{
    use DrainsCrossContextQueues;
    use Factories;
    use ResetDatabase;

    #[Test]
    public function owner_sends_an_invitation_and_a_duplicate_is_rejected(): void
    {
        // Arrange
        $client = self::createClient();
        $owner = AccountFactory::createOne(['email' => Email::fromString('owner@example.com')]);
        $this->drainCrossContextQueues();
        $this->authenticateAs($client, $owner);
        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme Inc.']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        // Act
        $client->jsonRequest('POST', '/api/organizations/invitations', ['email' => 'invitee@example.com']);

        // Assert
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('invitee@example.com', $response['email'] ?? null);
        Assert::assertIsString($response['id'] ?? null);
        Assert::assertIsString($response['expiresAt'] ?? null);

        // Act: inviting the same email again while pending is rejected
        $client->jsonRequest('POST', '/api/organizations/invitations', ['email' => 'invitee@example.com']);

        // Assert
        Assert::assertSame(409, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function non_owner_cannot_send_invitations(): void
    {
        // Arrange
        $client = self::createClient();
        $owner = AccountFactory::createOne(['email' => Email::fromString('owner2@example.com')]);
        $employeeAccount = AccountFactory::createOne(['email' => Email::fromString('employee2@example.com')]);
        $this->drainCrossContextQueues();

        $this->authenticateAs($client, $owner);
        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme Inc.']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $client->jsonRequest('POST', '/api/organizations/employees', ['email' => 'employee2@example.com']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        // Act: the non-owner employee tries to send an invitation
        $this->authenticateAs($client, $employeeAccount);
        $client->jsonRequest('POST', '/api/organizations/invitations', ['email' => 'someone@example.com']);

        // Assert
        Assert::assertSame(403, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function cannot_invite_someone_who_already_belongs_to_an_organization(): void
    {
        // Arrange
        $client = self::createClient();
        $owner = AccountFactory::createOne(['email' => Email::fromString('owner3@example.com')]);
        $busyAccount = AccountFactory::createOne(['email' => Email::fromString('busy@example.com')]);
        $this->drainCrossContextQueues();

        $this->authenticateAs($client, $busyAccount);
        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Other Org']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        $this->authenticateAs($client, $owner);
        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme Inc.']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        // Act
        $client->jsonRequest('POST', '/api/organizations/invitations', ['email' => 'busy@example.com']);

        // Assert
        Assert::assertSame(409, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function returns_unauthorized_when_not_authenticated(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->jsonRequest('POST', '/api/organizations/invitations', ['email' => 'invitee@example.com']);

        // Assert
        Assert::assertSame(401, $client->getResponse()->getStatusCode());
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
