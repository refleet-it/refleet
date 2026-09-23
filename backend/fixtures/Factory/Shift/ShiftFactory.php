<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Shift;

use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\ValueObject\AccountId;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class ShiftFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Shift::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id' => ShiftId::generate(),
            'organizationId' => OrganizationId::generate(),
            'title' => self::faker()->sentence(4),
            'description' => self::faker()->paragraph(),
            'createdBy' => AccountId::generate(),
            'qualificationId' => null,
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('draft'));
    }
}
