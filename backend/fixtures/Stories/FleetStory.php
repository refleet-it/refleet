<?php

declare(strict_types=1);

namespace App\Fixtures\Stories;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Fixtures\Factory\Identity\ApiKeyFactory;
use App\Fixtures\Factory\Organization\EmployeeFactory;
use App\Fixtures\Factory\Organization\GitLabConnectionFactory;
use App\Fixtures\Factory\Organization\OrganizationFactory;
use App\Fixtures\Factory\Project\ProjectFactory;
use App\Fixtures\Factory\Qualification\QualificationFactory;
use App\Fixtures\Factory\Shift\ShiftFactory;
use App\Identity\Account\Domain\Account\Enum\RoleEnum as AccountRoleEnum;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\AccountId as GitLabConnectionAccountId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId as GitLabConnectionOrganizationId;
use App\Organization\Organization\Domain\Employee\Enum\RoleEnum as EmployeeRoleEnum;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId as OrganizationAccountId;
use App\Project\Project\Domain\Project\ValueObject\GitLabProjectId;
use App\Project\Project\Domain\Project\ValueObject\OrganizationId as ProjectOrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\AccountId as QualificationAccountId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId as QualificationOrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\AccountId as ShiftAccountId;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId as ShiftOrganizationId;
use Zenstruck\Foundry\Story;

use function Zenstruck\Foundry\Persistence\save;

/**
 * A single organization ("Acme Robotics") wired up end to end across the Organization,
 * Project, Qualification, Shift and Runner registries, so the dashboard has believable, non-empty
 * data out of the box instead of every list page starting blank until someone clicks
 * through the create flows by hand.
 */
final class FleetStory extends Story
{
    public function build(): void
    {
        $owner = AccountFactory::createOne([
            'email' => Email::fromString('owner@acme-robotics.test'),
            'role' => AccountRoleEnum::USER,
            'hashedPassword' => HashedPassword::fromString(AccountFactory::TEST_HASHED_PASSWORD),
        ]);

        $devs = [
            AccountFactory::createOne([
                'email' => Email::fromString('dev1@acme-robotics.test'),
                'role' => AccountRoleEnum::USER,
                'hashedPassword' => HashedPassword::fromString(AccountFactory::TEST_HASHED_PASSWORD),
            ]),
            AccountFactory::createOne([
                'email' => Email::fromString('dev2@acme-robotics.test'),
                'role' => AccountRoleEnum::USER,
                'hashedPassword' => HashedPassword::fromString(AccountFactory::TEST_HASHED_PASSWORD),
            ]),
        ];

        ApiKeyFactory::createOne([
            'account' => $owner,
        ]);

        $organization = OrganizationFactory::createOne([
            'name' => 'Acme Robotics',
            'ownerAccountId' => OrganizationAccountId::fromString($owner->id()->asString()),
        ]);
        $organizationId = $organization->id();

        // The AccountCreated domain event (dispatched on flush, see DomainEventDispatcher)
        // already mirrors every new account into an Employee row via MirrorAccountAsEmployee
        // — findOrCreate() picks that row up instead of colliding with it on the same
        // account_id primary key.
        $ownerEmployee = EmployeeFactory::findOrCreate([
            'accountId' => OrganizationAccountId::fromString($owner->id()->asString()),
        ]);
        $ownerEmployee->joinOrganization($organizationId, EmployeeRoleEnum::OWNER);
        save($ownerEmployee);

        foreach ($devs as $dev) {
            $employee = EmployeeFactory::findOrCreate([
                'accountId' => OrganizationAccountId::fromString($dev->id()->asString()),
            ]);
            $employee->joinOrganization($organizationId, EmployeeRoleEnum::USER);
            save($employee);
        }

        // Invented ids: nothing here points at a real GitLab project.
        // (external ID, name, path) so connecting that group and syncing updates these
        // rows in place instead of creating duplicates.
        ProjectFactory::createOne([
            'organizationId' => ProjectOrganizationId::fromString($organizationId->asString()),
            'externalId' => GitLabProjectId::fromString('90000001'),
            'name' => 'Payments Service',
            'path' => 'acme-robotics/payments-service',
            'webUrl' => 'https://gitlab.com/acme-robotics/payments-service',
            'defaultBranch' => 'main',
            'description' => 'Handles payment processing and settlement for the fleet.',
        ]);
        ProjectFactory::createOne([
            'organizationId' => ProjectOrganizationId::fromString($organizationId->asString()),
            'externalId' => GitLabProjectId::fromString('90000002'),
            'name' => 'Checkout Service',
            'path' => 'acme-robotics/checkout-service',
            'webUrl' => 'https://gitlab.com/acme-robotics/checkout-service',
            'defaultBranch' => 'main',
            'description' => 'Manages cart and checkout flows.',
        ]);
        ProjectFactory::createOne([
            'organizationId' => ProjectOrganizationId::fromString($organizationId->asString()),
            'externalId' => GitLabProjectId::fromString('90000003'),
            'name' => 'Inventory Service',
            'path' => 'acme-robotics/inventory-service',
            'webUrl' => 'https://gitlab.com/acme-robotics/inventory-service',
            'defaultBranch' => 'main',
            'description' => 'Tracks warehouse inventory levels.',
        ]);
        ProjectFactory::createOne([
            'organizationId' => ProjectOrganizationId::fromString($organizationId->asString()),
            'externalId' => GitLabProjectId::fromString('90000004'),
            'name' => 'Notifications Service',
            'path' => 'acme-robotics/notifications-service',
            'webUrl' => 'https://gitlab.com/acme-robotics/notifications-service',
            'defaultBranch' => 'main',
            'description' => 'Sends transactional and marketing notifications.',
        ]);
        ProjectFactory::createOne([
            'organizationId' => ProjectOrganizationId::fromString($organizationId->asString()),
            'externalId' => GitLabProjectId::fromString('90000005'),
            'name' => 'Billing Service',
            'path' => 'acme-robotics/billing-service',
            'webUrl' => 'https://gitlab.com/acme-robotics/billing-service',
            'defaultBranch' => 'main',
            'description' => 'Handles invoicing and billing cycles.',
        ]);

        // Connection that "synced" the 5 projects above, so the dashboard and the
        // organization GitLab settings page both show GitLab as already connected
        // instead of prompting to connect it again.
        $gitLabConnection = GitLabConnectionFactory::createOne([
            'organizationId' => GitLabConnectionOrganizationId::fromString($organizationId->asString()),
            'groupPath' => 'acme-robotics',
            'groupName' => 'Acme Robotics',
            'connectedByAccountId' => GitLabConnectionAccountId::fromString($owner->id()->asString()),
        ]);
        $gitLabConnection->recordSyncSuccess(5);
        save($gitLabConnection);

        QualificationFactory::createOne([
            'organizationId' => QualificationOrganizationId::fromString($organizationId->asString()),
            'title' => 'Find projects using the legacy logging library',
            'createdBy' => QualificationAccountId::fromString($owner->id()->asString()),
        ]);

        ShiftFactory::createOne([
            'organizationId' => ShiftOrganizationId::fromString($organizationId->asString()),
            'title' => 'Bump legacy logging library',
            'createdBy' => ShiftAccountId::fromString($owner->id()->asString()),
        ]);

        // No fixture Runner rows: the registry is self-populating (find-or-create on
        // first heartbeat, see HeartbeatRunnerHandler) — the real runner container in
        // the dev compose stack (runner) registers itself once it's up and polling.
    }
}
