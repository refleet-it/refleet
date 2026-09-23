<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\DenyCliAuthorization;

use App\Identity\Account\Application\Command\DenyCliAuthorization\DenyCliAuthorizationCommand;
use App\Identity\Account\Application\Command\DenyCliAuthorization\DenyCliAuthorizationHandler;
use App\Identity\Account\Domain\CliAuthorization\Enum\CliAuthorizationStatusEnum;
use App\Identity\Account\Domain\CliAuthorization\Exception\CliAuthorizationNotFoundException;
use App\Identity\Account\Domain\CliAuthorization\Model\CliAuthorization;
use App\Identity\Account\Domain\CliAuthorization\Repository\CliAuthorizationRepositoryInterface;
use App\Identity\Account\Domain\CliAuthorization\ValueObject\CliAuthorizationId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DenyCliAuthorizationHandler::class)]
final class DenyCliAuthorizationHandlerTest extends TestCase
{
    #[Test]
    public function it_marks_the_authorization_denied(): void
    {
        // Arrange
        $authorization = CliAuthorization::create(CliAuthorizationId::generate(), 'user-code', 'hash', 'my-laptop', new \DateTimeImmutable('+10 minutes'));
        $repository = $this->createMock(CliAuthorizationRepositoryInterface::class);
        $repository->method('findByUserCode')->with('user-code')->willReturn($authorization);
        $repository->expects($this->once())->method('save')->with($authorization);

        // Act
        (new DenyCliAuthorizationHandler($repository))(new DenyCliAuthorizationCommand('user-code'));

        // Assert
        Assert::assertSame(CliAuthorizationStatusEnum::DENIED, $authorization->status());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function it_throws_for_an_unknown_code(): void
    {
        $repository = $this->createMock(CliAuthorizationRepositoryInterface::class);
        $repository->method('findByUserCode')->willReturn(null);

        $this->expectException(CliAuthorizationNotFoundException::class);

        (new DenyCliAuthorizationHandler($repository))(new DenyCliAuthorizationCommand('nope'));
    }
}
