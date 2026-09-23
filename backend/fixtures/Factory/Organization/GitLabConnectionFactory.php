<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Organization;

use App\Organization\GitLabConnection\Domain\Connection\Model\GitLabConnection;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\AccountId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabConnectionId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class GitLabConnectionFactory extends PersistentObjectFactory
{
    /**
     * libsodium ciphertext of "glpat-fixture-local-dev-token-0001", encrypted with the
     * local-dev GITLAB_TOKEN_ENCRYPTION_KEY from .env/.env.dist — pre-computed the same
     * way AccountFactory::TEST_HASHED_PASSWORD pre-computes its bcrypt hash, so loading
     * fixtures doesn't depend on wiring the real encryptor service into a factory.
     */
    public const string TEST_ACCESS_TOKEN_CIPHERTEXT = 'n7YyAOCeOD4boORhm0OFd1JnL8wj0rDOrBF28xJY4DpW/OyTKb93XxhaRAW3SQ6786JURE4ZSs6lNZhuyc1QpiEq8QqGx6pczZM=';

    public static function class(): string
    {
        return GitLabConnection::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id' => GitLabConnectionId::generate(),
            'organizationId' => OrganizationId::generate(),
            'baseUrl' => 'https://gitlab.com',
            'groupId' => (string) self::faker()->unique()->numberBetween(10000000, 99999999),
            'groupPath' => self::faker()->slug(),
            'groupName' => self::faker()->company(),
            'accessTokenCiphertext' => self::TEST_ACCESS_TOKEN_CIPHERTEXT,
            'connectedByAccountId' => AccountId::generate(),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('connect'));
    }
}
