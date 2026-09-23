<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\StartCliAuthorization;

use App\Identity\Account\Application\Command\StartCliAuthorization\StartCliAuthorizationCommand;
use App\Identity\Account\Application\Command\StartCliAuthorization\StartCliAuthorizationHandler;
use App\Identity\Account\Domain\CliAuthorization\Model\CliAuthorization;
use App\Identity\Account\Domain\CliAuthorization\Repository\CliAuthorizationRepositoryInterface;
use App\Identity\Account\Infrastructure\Security\CliAuthorizationCodeGenerator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StartCliAuthorizationHandler::class)]
final class StartCliAuthorizationHandlerTest extends TestCase
{
    #[Test]
    public function it_persists_only_the_hash_and_hands_the_secret_back_once(): void
    {
        // Arrange
        $repository = $this->createMock(CliAuthorizationRepositoryInterface::class);
        $repository->expects($this->once())->method('deleteExpiredBefore');
        $saved = null;
        $repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (CliAuthorization $authorization) use (&$saved): bool {
                $saved = $authorization;

                return true;
            }));
        $handler = new StartCliAuthorizationHandler($repository, new CliAuthorizationCodeGenerator());

        // Act
        $result = $handler(new StartCliAuthorizationCommand('my-laptop'));

        // Assert
        Assert::assertNotNull($saved);
        Assert::assertSame($result->userCode, $saved->userCode());
        Assert::assertSame('my-laptop', $saved->runnerName());
        Assert::assertSame(CliAuthorizationCodeGenerator::hash($result->deviceSecret), $saved->deviceSecretHash());
        Assert::assertTrue($saved->isPending());
        Assert::assertFalse($saved->isExpired());
        Assert::assertSame($saved->expiresAt()->format('c'), $result->expiresAt);
    }
}
