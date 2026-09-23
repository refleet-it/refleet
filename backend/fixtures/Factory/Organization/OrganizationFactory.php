<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Organization;

use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Organization\Model\Organization;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class OrganizationFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Organization::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id' => OrganizationId::generate(),
            'name' => self::faker()->company(),
            'ownerAccountId' => AccountId::generate(),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('create'));
    }
}
