<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Query\ListApiKeys;

use App\Identity\Account\Application\Query\ListApiKeys\ListApiKeysHandler;
use App\Identity\Account\Application\Query\ListApiKeys\ListApiKeysQuery;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\Account\Domain\ApiKey\Model\ApiKey;
use App\Identity\Account\Domain\ApiKey\Repository\ApiKeyRepositoryInterface;
use App\Identity\Account\Domain\ApiKey\ValueObject\ApiKeyId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The endpoint behind this query promises, in its own OpenAPI description, to list the keys of the
 * current account and never the secret. Both halves of that promise are held here: the scope the
 * repository is asked for, and what survives the mapping into the read model.
 */
#[CoversClass(ListApiKeysHandler::class)]
#[UsesClass(ListApiKeysQuery::class)]
#[UsesClass(ApiKey::class)]
#[UsesClass(Account::class)]
final class ListApiKeysHandlerTest extends TestCase
{
    private const string ACCOUNT_ID = '11111111-2222-3333-4444-555555555555';

    private const string SECRET = 'ib_live_the_actual_secret_value';

    #[Test]
    public function returns_nothing_when_the_account_has_no_keys(): void
    {
        // Arrange
        $handler = $this->handler([]);

        // Act
        $result = $handler(new ListApiKeysQuery(self::ACCOUNT_ID));

        // Assert
        Assert::assertSame([], $result);
    }

    // Listing is the whole authorization boundary here: the handler has no other filter, so asking
    // the repository for anything but the account in the query hands one account another's keys.
    #[Test]
    public function asks_the_repository_for_the_account_named_in_the_query(): void
    {
        // Arrange
        $repository = $this->createMock(ApiKeyRepositoryInterface::class);
        $repository
            ->expects($this->once())
            ->method('findAllByAccountId')
            ->with(self::ACCOUNT_ID)
            ->willReturn([]);

        $handler = new ListApiKeysHandler($repository);

        // Act
        $handler(new ListApiKeysQuery(self::ACCOUNT_ID));
    }

    #[Test]
    public function maps_a_key_onto_the_read_model(): void
    {
        // Arrange
        $apiKey = $this->apiKey('CI runner', 'ib_live_abcd');
        $handler = $this->handler([$apiKey]);

        // Act
        $result = $handler(new ListApiKeysQuery(self::ACCOUNT_ID));

        // Assert
        Assert::assertCount(1, $result);
        Assert::assertSame($apiKey->id()->asString(), $result[0]->id);
        Assert::assertSame('CI runner', $result[0]->name);
        Assert::assertSame('ib_live_abcd', $result[0]->prefix);
        Assert::assertSame($apiKey->createdAt()->format('c'), $result[0]->createdAt);
    }

    // A key that has never been called is the normal state right after it is issued, so this is the
    // common case rather than an edge one. Formatting the missing timestamps instead of passing the
    // nulls through would either fail outright or claim the key had just been used.
    #[Test]
    public function reports_nulls_for_a_key_that_was_never_used_or_revoked(): void
    {
        // Arrange
        $handler = $this->handler([$this->apiKey()]);

        // Act
        $result = $handler(new ListApiKeysQuery(self::ACCOUNT_ID));

        // Assert
        Assert::assertNull($result[0]->lastUsedAt);
        Assert::assertNull($result[0]->revokedAt);
    }

    // Revoked keys stay in the listing on purpose — dropping them would silently erase the record
    // that the key ever existed, which is the one thing worth seeing after a leak.
    #[Test]
    public function keeps_revoked_keys_in_the_listing_with_the_moment_they_were_revoked(): void
    {
        // Arrange
        $apiKey = $this->apiKey();
        $apiKey->revoke();

        $handler = $this->handler([$apiKey]);

        // Act
        $result = $handler(new ListApiKeysQuery(self::ACCOUNT_ID));

        // Assert
        Assert::assertCount(1, $result);
        Assert::assertNotNull($result[0]->revokedAt);
        Assert::assertSame($apiKey->revokedAt()?->format('c'), $result[0]->revokedAt);
    }

    #[Test]
    public function never_carries_the_secret_or_its_hash_into_the_read_model(): void
    {
        // Arrange
        $handler = $this->handler([$this->apiKey()]);

        // Act
        $result = $handler(new ListApiKeysQuery(self::ACCOUNT_ID));

        // Assert
        foreach (\get_object_vars($result[0]) as $field => $value) {
            Assert::assertStringNotContainsString(self::SECRET, (string) $value, $field.' carries the secret');
            Assert::assertStringNotContainsString(\hash('sha256', self::SECRET), (string) $value, $field.' carries the hashed secret');
        }
    }

    /**
     * @param ApiKey[] $found
     */
    private function handler(array $found): ListApiKeysHandler
    {
        $repository = $this->createStub(ApiKeyRepositoryInterface::class);
        $repository->method('findAllByAccountId')->willReturn($found);

        return new ListApiKeysHandler($repository);
    }

    private function apiKey(string $name = 'Local runner', string $prefix = 'ib_live_0000'): ApiKey
    {
        $account = Account::create(
            AccountId::fromString(self::ACCOUNT_ID),
            Email::fromString('owner@refleet.it'),
            HashedPassword::fromString('hashed-password'),
            RoleEnum::USER,
        );

        return ApiKey::create(
            ApiKeyId::generate(),
            $account,
            $name,
            $prefix,
            \hash('sha256', self::SECRET),
        );
    }
}
