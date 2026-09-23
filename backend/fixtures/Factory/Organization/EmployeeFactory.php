<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Organization;

use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class EmployeeFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Employee::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'accountId' => AccountId::generate(),
            'email' => self::faker()->email(),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('mirror'));
    }
}
