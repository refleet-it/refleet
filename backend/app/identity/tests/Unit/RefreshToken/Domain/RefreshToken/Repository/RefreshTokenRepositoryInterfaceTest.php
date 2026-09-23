<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\RefreshToken\Domain\RefreshToken\Repository;

use App\Identity\RefreshToken\Domain\RefreshToken\Model\RefreshToken;
use App\Identity\RefreshToken\Domain\RefreshToken\Repository\RefreshTokenRepositoryInterface;
use App\Identity\RefreshToken\Infrastructure\Persistence\DoctrineRefreshTokenRepository;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RefreshTokenRepositoryInterfaceTest extends TestCase
{
    #[Test]
    public function interface_is_defined_and_public(): void
    {
        Assert::assertTrue(\interface_exists(RefreshTokenRepositoryInterface::class));

        $ref = new \ReflectionClass(RefreshTokenRepositoryInterface::class);
        Assert::assertTrue($ref->isInterface());
        Assert::assertFalse($ref->isInternal()); // userland interface
    }

    #[Test]
    public function interface_exposes_expected_methods(): void
    {
        $ref = new \ReflectionClass(RefreshTokenRepositoryInterface::class);

        $methods = $ref->getMethods();
        Assert::assertCount(1, $methods);
        $methodNames = \array_map(static fn (\ReflectionMethod $method) => $method->getName(), $methods);
        Assert::assertSame(['findByToken'], $methodNames);
    }

    #[Test]
    public function find_by_token_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(RefreshTokenRepositoryInterface::class, 'findByToken');
        Assert::assertTrue($method->isPublic());

        $params = $method->getParameters();
        Assert::assertCount(1, $params);
        $this->assertParamType($params[0], 'string', false);

        $this->assertReturnType($method, RefreshToken::class, true);
    }

    #[Test]
    public function doctrine_repository_implements_interface(): void
    {
        Assert::assertTrue(
            \is_subclass_of(
                DoctrineRefreshTokenRepository::class,
                RefreshTokenRepositoryInterface::class,
            ),
        );
    }

    private function assertParamType(\ReflectionParameter $param, string $expectedType, bool $allowsNull): void
    {
        $type = $param->getType();
        Assert::assertInstanceOf(\ReflectionNamedType::class, $type);
        /* @var \ReflectionNamedType $type */
        Assert::assertSame($expectedType, $type->getName());
        Assert::assertSame($allowsNull, $type->allowsNull());
    }

    private function assertReturnType(\ReflectionMethod $method, string $expectedType, bool $allowsNull): void
    {
        $type = $method->getReturnType();
        Assert::assertInstanceOf(\ReflectionNamedType::class, $type);
        /* @var \ReflectionNamedType $type */
        Assert::assertSame($expectedType, $type->getName());
        Assert::assertSame($allowsNull, $type->allowsNull());
    }
}
