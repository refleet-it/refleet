<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Bus\RunnerArchived;

use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\ApiKey\Model\ApiKey;
use App\Identity\Account\Domain\ApiKey\Repository\ApiKeyRepositoryInterface;
use App\Identity\Account\Domain\ApiKey\ValueObject\ApiKeyId;
use App\Identity\Account\Infrastructure\Bus\RunnerArchived\RunnerArchivedMessage;
use App\Identity\Account\Infrastructure\Bus\RunnerArchived\RunnerArchivedMessageHandler;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(RunnerArchivedMessageHandler::class)]
final class RunnerArchivedMessageHandlerTest extends TestCase
{
    private ApiKeyRepositoryInterface&MockObject $apiKeys;

    private RunnerArchivedMessageHandler $handler;

    #[Test]
    public function revokes_the_key_the_archived_runner_authenticated_with(): void
    {
        // Arrange
        $apiKeyId = ApiKeyId::generate();
        $apiKey = ApiKey::create(
            id: $apiKeyId,
            account: $this->createStub(Account::class),
            name: 'runner key',
            keyPrefix: 'ib_prefix',
            hashedSecret: \str_repeat('a', 64),
        );

        $this->apiKeys->method('findById')->willReturn($apiKey);
        $this->apiKeys->expects($this->once())->method('save')->with($apiKey);

        // Act
        ($this->handler)(new RunnerArchivedMessage(
            runnerId: 'runner-1',
            organizationId: 'org-1',
            apiKeyId: $apiKeyId->asString(),
        ));

        // Assert
        Assert::assertTrue($apiKey->isRevoked());
    }

    #[Test]
    public function does_nothing_when_the_runner_had_no_key_on_record(): void
    {
        // Arrange
        $this->apiKeys->expects($this->never())->method('findById');
        $this->apiKeys->expects($this->never())->method('save');

        // Act
        ($this->handler)(new RunnerArchivedMessage(
            runnerId: 'runner-1',
            organizationId: 'org-1',
            apiKeyId: null,
        ));
    }

    #[Test]
    public function tolerates_a_key_that_no_longer_exists(): void
    {
        // Arrange
        $this->apiKeys->method('findById')->willReturn(null);
        $this->apiKeys->expects($this->never())->method('save');

        // Act
        ($this->handler)(new RunnerArchivedMessage(
            runnerId: 'runner-1',
            organizationId: 'org-1',
            apiKeyId: ApiKeyId::generate()->asString(),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->apiKeys = $this->createMock(ApiKeyRepositoryInterface::class);
        $this->handler = new RunnerArchivedMessageHandler($this->apiKeys, new NullLogger());
    }
}
