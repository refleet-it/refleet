<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Service;

use App\Identity\Account\Infrastructure\Security\SymfonyPasswordHasher;
use App\Identity\Account\Infrastructure\Service\PasswordHasher;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PasswordHasherTest extends TestCase
{
    #[Test]
    public function interface_is_defined_and_public(): void
    {
        Assert::assertTrue(\interface_exists(PasswordHasher::class));

        $ref = new \ReflectionClass(PasswordHasher::class);
        Assert::assertTrue($ref->isInterface());
        Assert::assertFalse($ref->isInternal()); // userland interface
    }

    #[Test]
    public function hash_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(PasswordHasher::class, 'hash');
        Assert::assertTrue($method->isPublic());

        $params = $method->getParameters();
        Assert::assertCount(1, $params);
        $this->assertParamType($params[0], 'string', false);

        $this->assertReturnType($method, 'string', false);
    }

    #[Test]
    public function verify_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(PasswordHasher::class, 'verify');
        Assert::assertTrue($method->isPublic());

        $params = $method->getParameters();
        Assert::assertCount(2, $params);
        $this->assertParamType($params[0], 'string', false);
        $this->assertParamType($params[1], 'string', false);

        $this->assertReturnType($method, 'bool', false);
    }

    #[Test]
    public function infrastructure_implementation_implements_interface(): void
    {
        Assert::assertTrue(\is_subclass_of(SymfonyPasswordHasher::class, PasswordHasher::class));
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
