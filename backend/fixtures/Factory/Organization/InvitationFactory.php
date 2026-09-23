<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Organization;

use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Invitation\Model\Invitation;
use App\Organization\Organization\Domain\Invitation\ValueObject\InvitationId;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Shared\Domain\ValueObject\Id;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class InvitationFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Invitation::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id' => InvitationId::generate(),
            'organizationId' => OrganizationId::generate(),
            'email' => self::faker()->email(),
            'role' => RoleEnum::USER,
            'invitedByAccountId' => Id::generate()->asString(),
            'token' => \bin2hex(\random_bytes(32)),
            'expiresAt' => new \DateTimeImmutable('+7 days'),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('create'));
    }
}
