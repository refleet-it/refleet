<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Command\Sync;

use App\Shared\Application\Command\Sync\CommandBusInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CommandBusInterfaceTest extends TestCase
{
    #[Test]
    public function interface_is_defined_and_public(): void
    {
        Assert::assertTrue(\interface_exists(CommandBusInterface::class));

        $ref = new \ReflectionClass(CommandBusInterface::class);
        Assert::assertTrue($ref->isInterface());
        Assert::assertFalse($ref->isInternal()); // userland interface
    }

    #[Test]
    public function has_single_dispatch_method(): void
    {
        $ref = new \ReflectionClass(CommandBusInterface::class);

        Assert::assertCount(1, $ref->getMethods());
        Assert::assertTrue($ref->hasMethod('dispatch'));
    }

    #[Test]
    public function dispatch_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(CommandBusInterface::class, 'dispatch');
        Assert::assertTrue($method->isPublic());

        $params = $method->getParameters();
        Assert::assertCount(1, $params);
        $this->assertParamType($params[0], 'object', false);

        $this->assertReturnType($method, 'void', false);
        $this->assertDocblockContains($method, '@template T of object');
        $this->assertDocblockContains($method, '@param T $command');
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

    private function assertDocblockContains(\ReflectionMethod $method, string $expectedSubstring): void
    {
        $doc = (string) $method->getDocComment();
        Assert::assertNotSame('', $doc, \sprintf('%s should have a docblock', $method->getName()));
        Assert::assertStringContainsString($expectedSubstring, $doc);
    }
}
