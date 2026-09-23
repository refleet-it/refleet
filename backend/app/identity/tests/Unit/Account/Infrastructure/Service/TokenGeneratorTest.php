<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Service;

use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Infrastructure\Security\LcobucciTokenGenerator;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TokenGeneratorTest extends TestCase
{
    #[Test]
    public function interface_is_defined_and_public(): void
    {
        Assert::assertTrue(\interface_exists(TokenGenerator::class));

        $ref = new \ReflectionClass(TokenGenerator::class);
        Assert::assertTrue($ref->isInterface());
        Assert::assertFalse($ref->isInternal()); // userland interface
    }

    #[Test]
    public function generate_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(TokenGenerator::class, 'generate');
        Assert::assertTrue($method->isPublic());

        $params = $method->getParameters();
        Assert::assertCount(2, $params);

        // Account $account
        $this->assertParamType($params[0], Account::class, false);
        Assert::assertFalse($params[0]->isDefaultValueAvailable());

        // ?AccountId $impersonatorId = null
        $this->assertParamType($params[1], AccountId::class, true);
        Assert::assertTrue($params[1]->isDefaultValueAvailable());
        Assert::assertNull($params[1]->getDefaultValue());

        $this->assertReturnType($method, 'string', false);
    }

    #[Test]
    public function interface_only_defines_generate_method(): void
    {
        $ref = new \ReflectionClass(TokenGenerator::class);
        $methods = $ref->getMethods();

        Assert::assertCount(1, $methods);

        $method = $methods[0];
        Assert::assertSame('generate', $method->getName());
        Assert::assertTrue($method->isPublic());
        Assert::assertFalse($method->isStatic());
    }

    #[Test]
    public function infrastructure_implementation_implements_interface(): void
    {
        Assert::assertTrue(\is_subclass_of(LcobucciTokenGenerator::class, TokenGenerator::class));
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
