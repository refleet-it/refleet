<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Api;

use App\Identity\Account\Domain\Account\Enum\AccountStatusEnum;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\Account\Infrastructure\Api\ListAccountsController;
use App\Shared\Domain\ValueObject\CursorListParameters;
use App\Shared\Domain\ValueObject\CursorListResponse;
use App\Shared\Domain\ValueObject\Filtering\FilterOperator;
use App\Shared\Domain\ValueObject\Pagination\CursorPaginationParameters;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ListAccountsController::class)]
#[CoversClass(\App\Identity\Account\Infrastructure\Api\AccountReadModel::class)]
final class ListAccountsControllerTest extends TestCase
{
    private AccountRepositoryInterface $accountRepository;

    #[Test]
    public function lists_accounts_and_returns_mapped_read_models_with_pagination(): void
    {
        // Given
        $decodedCursor = 'last_account_id';
        $encodedCursor = \base64_encode(\json_encode(['id' => $decodedCursor, 'timestamp' => 1723612345]));

        $request = new Request(query: [
            'limit' => '2',
            'cursor' => $encodedCursor,
            'sortBy' => 'email',
            'sortDirection' => 'desc',
            'role' => 'manager', // allowed simple filter
        ]);

        $account1 = Account::create(
            AccountId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            Email::fromString('alice@example.com'),
            HashedPassword::fromString('hash1'),
            RoleEnum::USER,
            AccountStatusEnum::ACTIVE,
        );
        $account2 = Account::create(
            AccountId::fromString('550e8400-e29b-41d4-a716-446655440002'),
            Email::fromString('bob@example.com'),
            HashedPassword::fromString('hash2'),
            RoleEnum::USER,
            AccountStatusEnum::ACTIVE,
        );
        $account2->deactivate();

        $expectedNextCursor = 'next_cursor_123';

        $this->accountRepository
            ->expects($this->once())
            ->method('getPaginatedList')
            ->willReturnCallback(static function (CursorListParameters $params) use ($decodedCursor, $account1, $account2, $expectedNextCursor): CursorListResponse {
                $pagination = $params->getPagination();
                Assert::assertSame(2, $pagination->getLimit());
                Assert::assertSame($decodedCursor, $pagination->getCursor());

                $sorting = $params->getSorting();
                Assert::assertNotNull($sorting);
                Assert::assertSame('email', $sorting->getField());
                Assert::assertTrue($sorting->isDescending());

                $filtering = $params->getFiltering();
                Assert::assertTrue($params->hasFiltering());
                $roleFilters = $filtering->getByField('role');
                Assert::assertNotEmpty($roleFilters);
                $first = \array_first($roleFilters);
                Assert::assertSame('role', $first->getField());
                Assert::assertSame(FilterOperator::EQUALS, $first->getOperator());
                Assert::assertSame('manager', $first->getValue());

                return CursorListResponse::create(
                    items: [$account1, $account2],
                    pagination: CursorPaginationParameters::fromRequest(cursor: $decodedCursor, limit: 2),
                    nextCursor: $expectedNextCursor,
                    hasNextPage: true,
                );
            });

        $controller = new ListAccountsController($this->accountRepository);

        // When
        $response = $controller($request);

        // Then
        Assert::assertSame(200, $response->getStatusCode());

        $payload = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        Assert::assertArrayHasKey('data', $payload);
        Assert::assertArrayHasKey('pagination', $payload);

        // Data assertions
        Assert::assertCount(2, $payload['data']);
        $first = $payload['data'][0];
        $second = $payload['data'][1];

        Assert::assertSame($account1->id()->asString(), $first['id']);
        Assert::assertSame('alice@example.com', $first['email']);
        Assert::assertSame('user', $first['role']);
        Assert::assertSame('active', $first['status']);
        Assert::assertArrayHasKey('createdAt', $first);
        Assert::assertArrayHasKey('updatedAt', $first);

        Assert::assertSame($account2->id()->asString(), $second['id']);
        Assert::assertSame('bob@example.com', $second['email']);
        Assert::assertSame('user', $second['role']);
        Assert::assertSame('inactive', $second['status']);
        Assert::assertArrayHasKey('createdAt', $second);
        Assert::assertArrayHasKey('updatedAt', $second);

        // Pagination assertions
        Assert::assertTrue($payload['pagination']['hasNextPage']);
        Assert::assertSame($expectedNextCursor, $payload['pagination']['nextCursor']);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->accountRepository = $this->createMock(AccountRepositoryInterface::class);
    }
}
