<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Qualification;

use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\AccountId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationCriteria;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class QualificationFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Qualification::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id' => QualificationId::generate(),
            'organizationId' => OrganizationId::generate(),
            'title' => self::faker()->sentence(4),
            'description' => self::faker()->paragraph(),
            'createdBy' => AccountId::generate(),
            'criteria' => QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?'),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('draft'));
    }
}
