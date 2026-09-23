<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Query\GetCliAuthorization;

use App\Identity\Account\Application\Query\GetCliAuthorization\GetCliAuthorizationHandler;
use App\Identity\Account\Application\Query\GetCliAuthorization\GetCliAuthorizationQuery;
use App\Identity\Account\Domain\CliAuthorization\Exception\CliAuthorizationExpiredException;
use App\Identity\Account\Domain\CliAuthorization\Exception\CliAuthorizationNotFoundException;
use App\Identity\Account\Domain\CliAuthorization\Model\CliAuthorization;
use App\Identity\Account\Domain\CliAuthorization\Repository\CliAuthorizationRepositoryInterface;
use App\Identity\Account\Domain\CliAuthorization\ValueObject\CliAuthorizationId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetCliAuthorizationHandler::class)]
final class GetCliAuthorizationHandlerTest extends TestCase
{
    #[Test]
    public function it_describes_a_pending_authorization_without_leaking_secrets(): void
    {
        $authorization = CliAuthorization::create(CliAuthorizationId::generate(), 'user-code', 'hash', 'my-laptop', new \DateTimeImmutable('+10 minutes'));
        $repository = $this->createStub(CliAuthorizationRepositoryInterface::class);
        $repository->method('findByUserCode')->willReturn($authorization);

        $result = (new GetCliAuthorizationHandler($repository))(new GetCliAuthorizationQuery('user-code'));

        Assert::assertSame('my-laptop', $result->runnerName);
        Assert::assertSame('pending', $result->status);
        Assert::assertSame($authorization->expiresAt()->format('c'), $result->expiresAt);
    }

    #[Test]
    public function it_throws_for_an_unknown_code(): void
    {
        $repository = $this->createStub(CliAuthorizationRepositoryInterface::class);
        $repository->method('findByUserCode')->willReturn(null);

        $this->expectException(CliAuthorizationNotFoundException::class);

        (new GetCliAuthorizationHandler($repository))(new GetCliAuthorizationQuery('nope'));
    }

    #[Test]
    public function it_reports_an_expired_authorization_as_gone(): void
    {
        $authorization = CliAuthorization::create(CliAuthorizationId::generate(), 'user-code', 'hash', 'my-laptop', new \DateTimeImmutable('-1 second'));
        $repository = $this->createStub(CliAuthorizationRepositoryInterface::class);
        $repository->method('findByUserCode')->willReturn($authorization);

        $this->expectException(CliAuthorizationExpiredException::class);

        (new GetCliAuthorizationHandler($repository))(new GetCliAuthorizationQuery('user-code'));
    }
}
