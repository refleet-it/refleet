<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Identity;

use App\Identity\Account\Domain\ApiKey\Model\ApiKey;
use App\Identity\Account\Domain\ApiKey\ValueObject\ApiKeyId;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class ApiKeyFactory extends PersistentObjectFactory
{
    /**
     * Fixed local-dev token so the "runner" compose service (REFLEET_API_KEY in .env)
     * never has to be regenerated after `make init` / `db-recreate` reloads fixtures.
     * Committed in the open on purpose — local dev only, never valid outside a fixture
     * database. See runner/.env.dist / .env.dist.
     */
    public const string TEST_TOKEN = 'ib_local_dev_fixture_static_runner_token_0001';

    public static function class(): string
    {
        return ApiKey::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id' => ApiKeyId::generate(),
            'account' => AccountFactory::new(),
            'name' => 'Local Runner (fixture)',
            'keyPrefix' => \substr(self::TEST_TOKEN, 0, 12),
            'hashedSecret' => \hash('sha256', self::TEST_TOKEN),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('create'));
    }
}
