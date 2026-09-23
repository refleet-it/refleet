<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Identity;

use App\Identity\RefreshToken\Domain\RefreshToken\Model\RefreshToken;
use App\Identity\RefreshToken\Domain\RefreshToken\ValueObject\RefreshTokenId;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class RefreshTokenFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return RefreshToken::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id' => RefreshTokenId::generate(),
            'account' => AccountFactory::new(),
            'token' => \bin2hex(\random_bytes(64)),
            'expiresAt' => new \DateTimeImmutable('+1 week'),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('create'));
    }
}
