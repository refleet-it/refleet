<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Identity;

use App\Identity\Account\Domain\Account\Enum\AccountStatusEnum;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class AccountFactory extends PersistentObjectFactory
{
    /** bcrypt cost=4 hash of "password123" — pre-computed to avoid slow hashing in fixtures */
    public const TEST_HASHED_PASSWORD = '$2y$04$1y1Y4DxnkK8SiPu1buX7E.RJz.heahkSWUy8AvuFJBXmrTLCFQCJ2';

    public static function class(): string
    {
        return Account::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id' => AccountId::generate(),
            'email' => Email::fromString(self::faker()->email()),
            'hashedPassword' => HashedPassword::fromString(self::TEST_HASHED_PASSWORD),
            'role' => RoleEnum::USER,
            'status' => AccountStatusEnum::ACTIVE,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('create'));
    }
}
