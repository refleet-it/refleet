<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\ClaimCliAuthorization;

use App\Identity\Account\Application\Command\ClaimCliAuthorization\ClaimCliAuthorizationCommand;
use App\Identity\Account\Application\Command\ClaimCliAuthorization\ClaimCliAuthorizationHandler;
use App\Identity\Account\Application\Command\ClaimCliAuthorization\ClaimedCliAuthorization;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\Account\Domain\ApiKey\Model\ApiKey;
use App\Identity\Account\Domain\ApiKey\Repository\ApiKeyRepositoryInterface;
use App\Identity\Account\Domain\CliAuthorization\Exception\CliAuthorizationNotFoundException;
use App\Identity\Account\Domain\CliAuthorization\Model\CliAuthorization;
use App\Identity\Account\Domain\CliAuthorization\Repository\CliAuthorizationRepositoryInterface;
use App\Identity\Account\Domain\CliAuthorization\ValueObject\CliAuthorizationId;
use App\Identity\Account\Infrastructure\Security\ApiKeyTokenGenerator;
use App\Identity\Account\Infrastructure\Security\CliAuthorizationCodeGenerator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClaimCliAuthorizationHandler::class)]
final class ClaimCliAuthorizationHandlerTest extends TestCase
{
    private const string SECRET = 'device-secret';

    private CliAuthorizationRepositoryInterface&MockObject $authorizations;

    private ApiKeyRepositoryInterface&MockObject $apiKeys;

    private ClaimCliAuthorizationHandler $handler;

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function a_pending_authorization_stays_put_and_reports_pending(): void
    {
        $this->authorizations->method('findByDeviceSecretHash')
            ->with(CliAuthorizationCodeGenerator::hash(self::SECRET))
            ->willReturn($this->authorization());
        $this->authorizations->expects($this->never())->method('delete');

        $result = ($this->handler)(new ClaimCliAuthorizationCommand(self::SECRET, 'refleet-runner (laptop)'));

        Assert::assertSame(ClaimedCliAuthorization::PENDING, $result->status);
        Assert::assertNull($result->apiKey);
    }

    #[Test]
    public function an_approved_authorization_mints_a_key_for_the_approving_account_and_is_consumed(): void
    {
        // Arrange
        $account = $this->account();
        $authorization = $this->authorization();
        $authorization->approve($account);
        $this->authorizations->method('findByDeviceSecretHash')->willReturn($authorization);
        $this->authorizations->expects($this->once())->method('delete')->with($authorization);

        $savedKey = null;
        $this->apiKeys
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (ApiKey $apiKey) use (&$savedKey): bool {
                $savedKey = $apiKey;

                return true;
            }));

        // Act
        $result = ($this->handler)(new ClaimCliAuthorizationCommand(self::SECRET, 'refleet-runner (laptop)'));

        // Assert
        Assert::assertSame(ClaimedCliAuthorization::APPROVED, $result->status);
        Assert::assertSame('owner@example.com', $result->accountEmail);
        Assert::assertNotNull($result->apiKey);
        Assert::assertNotNull($savedKey);
        Assert::assertSame($account->id()->asString(), $savedKey->accountId());
        Assert::assertSame('refleet-runner (laptop)', $savedKey->name());
        Assert::assertStringStartsWith(ApiKeyTokenGenerator::PREFIX, $result->apiKey->token);
        Assert::assertSame($savedKey->id()->asString(), $result->apiKey->id);
    }

    #[Test]
    public function a_denied_authorization_is_reported_once_and_removed(): void
    {
        $authorization = $this->authorization();
        $authorization->deny();
        $this->authorizations->method('findByDeviceSecretHash')->willReturn($authorization);
        $this->authorizations->expects($this->once())->method('delete')->with($authorization);
        $this->apiKeys->expects($this->never())->method('save');

        $result = ($this->handler)(new ClaimCliAuthorizationCommand(self::SECRET, 'x'));

        Assert::assertSame(ClaimedCliAuthorization::DENIED, $result->status);
    }

    #[Test]
    public function an_expired_authorization_is_reported_as_expired_and_removed(): void
    {
        $authorization = CliAuthorization::create(CliAuthorizationId::generate(), 'user-code', 'hash', 'laptop', new \DateTimeImmutable('-1 second'));
        $this->authorizations->method('findByDeviceSecretHash')->willReturn($authorization);
        $this->authorizations->expects($this->once())->method('delete')->with($authorization);
        $this->apiKeys->expects($this->never())->method('save');

        $result = ($this->handler)(new ClaimCliAuthorizationCommand(self::SECRET, 'x'));

        Assert::assertSame(ClaimedCliAuthorization::EXPIRED, $result->status);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function an_unknown_secret_is_not_found(): void
    {
        $this->authorizations->method('findByDeviceSecretHash')->willReturn(null);

        $this->expectException(CliAuthorizationNotFoundException::class);

        ($this->handler)(new ClaimCliAuthorizationCommand('wrong', 'x'));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->authorizations = $this->createMock(CliAuthorizationRepositoryInterface::class);
        $this->apiKeys = $this->createMock(ApiKeyRepositoryInterface::class);

        $this->handler = new ClaimCliAuthorizationHandler($this->authorizations, $this->apiKeys, new ApiKeyTokenGenerator());
    }

    private function authorization(): CliAuthorization
    {
        return CliAuthorization::create(CliAuthorizationId::generate(), 'user-code', 'hash', 'laptop', new \DateTimeImmutable('+10 minutes'));
    }

    private function account(): Account
    {
        return Account::create(AccountId::generate(), Email::fromString('owner@example.com'), HashedPassword::fromString('stored-hash'), RoleEnum::USER);
    }
}
