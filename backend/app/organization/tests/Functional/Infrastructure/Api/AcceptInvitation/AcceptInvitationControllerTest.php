<?php

declare(strict_types=1);

namespace App\Tests\Functional\Organization\Infrastructure\Api\AcceptInvitation;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Fixtures\Factory\Organization\InvitationFactory;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Infrastructure\Factory\AccountUserFactory;
use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Tests\Helpers\Messenger\DrainsCrossContextQueues;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class AcceptInvitationControllerTest extends WebTestCase
{
    use DrainsCrossContextQueues;
    use Factories;
    use ResetDatabase;

    #[Test]
    public function accepts_invitation_creates_account_and_joins_organization(): void
    {
        // Arrange: owner creates an organization
        $client = self::createClient();
        $owner = AccountFactory::createOne(['email' => Email::fromString('owner@example.com')]);
        $this->drainCrossContextQueues();
        $client->loginUser((new AccountUserFactory())->createFromAccount($owner));
        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme Inc.']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $organization = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $organizationId = OrganizationId::fromString((string) $organization['id']);

        $invitation = InvitationFactory::createOne([
            'organizationId' => $organizationId,
            'email' => 'invitee@example.com',
            'role' => RoleEnum::USER,
            'token' => 'a-valid-token',
        ]);

        // Act: accept the invitation - the endpoint is public and ignores the
        // caller's own auth state, so the same client can be reused.
        $client->jsonRequest('POST', '/api/organizations/invitations/accept', [
            'token' => 'a-valid-token',
            'password' => 'BrandNewPassword1!',
        ]);
        $this->drainCrossContextQueues();

        // Assert
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('invitee@example.com', $response['email'] ?? null);

        // The new account can log in immediately (no email verification needed)
        $client->jsonRequest('POST', '/api/identity/login', [
            'email' => 'invitee@example.com',
            'password' => 'BrandNewPassword1!',
        ]);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());

        // And is now a member of the organization
        $client->jsonRequest('GET', '/api/organizations/employees');
        $employees = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertCount(2, $employees['employees'] ?? []);
    }

    #[Test]
    public function rejects_an_unknown_token(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->jsonRequest('POST', '/api/organizations/invitations/accept', [
            'token' => 'does-not-exist',
            'password' => 'BrandNewPassword1!',
        ]);
        $this->drainCrossContextQueues();

        // Assert
        Assert::assertSame(404, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function rejects_an_expired_invitation(): void
    {
        // Arrange
        $client = self::createClient();
        InvitationFactory::createOne([
            'email' => 'late@example.com',
            'token' => 'expired-token',
            'expiresAt' => new \DateTimeImmutable('-1 minute'),
        ]);

        // Act
        $client->jsonRequest('POST', '/api/organizations/invitations/accept', [
            'token' => 'expired-token',
            'password' => 'BrandNewPassword1!',
        ]);
        $this->drainCrossContextQueues();

        // Assert
        Assert::assertSame(400, $client->getResponse()->getStatusCode());
    }
}
