<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Organization\Domain\Organization\Model;

use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Organization\Event\OrganizationCreated;
use App\Organization\Organization\Domain\Organization\Model\Organization;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Organization::class)]
final class OrganizationTest extends TestCase
{
    #[Test]
    public function creates_organization_with_owner(): void
    {
        $id = OrganizationId::generate();
        $ownerAccountId = AccountId::generate();

        $organization = Organization::create($id, 'Acme Inc.', $ownerAccountId);

        Assert::assertTrue($organization->id()->equals($id));
        Assert::assertSame('Acme Inc.', $organization->name());
        Assert::assertTrue($organization->ownerAccountId()->equals($ownerAccountId));
    }

    #[Test]
    public function records_organization_created_event(): void
    {
        $id = OrganizationId::generate();

        $organization = Organization::create($id, 'Acme Inc.', AccountId::generate());

        $events = $organization->getRecordedDomainEvents();

        Assert::assertCount(1, $events);
        Assert::assertInstanceOf(OrganizationCreated::class, $events[0]);
        Assert::assertTrue($events[0]->organizationId->equals($id));
        Assert::assertSame('Acme Inc.', $events[0]->name);
    }
}
