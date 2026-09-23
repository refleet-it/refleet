<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Identity;

use App\Identity\Account\Domain\Account\Model\PasswordResetToken;
use App\Shared\Domain\ValueObject\Id;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class PasswordResetTokenFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return PasswordResetToken::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id' => Id::generate(),
            'account' => AccountFactory::new(),
            'token' => \bin2hex(\random_bytes(32)),
            'expiresAt' => new \DateTimeImmutable('+1 hour'),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('create'));
    }
}
