<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Identity;

use App\Identity\Account\Domain\Account\Enum\AccountStatusEnum;
use App\Identity\Account\Domain\Account\Model\EmailVerificationToken;
use App\Shared\Domain\ValueObject\Id;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class EmailVerificationTokenFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return EmailVerificationToken::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id' => Id::generate(),
            'account' => AccountFactory::new([
                'status' => AccountStatusEnum::PENDING_EMAIL_VERIFICATION,
            ]),
            'token' => \bin2hex(\random_bytes(32)),
            'expiresAt' => new \DateTimeImmutable('+12 hours'),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('create'));
    }
}
