<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\User;

use App\Shared\Domain\User\UserInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserInterfaceTest extends TestCase
{
    #[Test]
    public function interface_is_defined_and_public(): void
    {
        Assert::assertTrue(\interface_exists(UserInterface::class));

        $ref = new \ReflectionClass(UserInterface::class);
        Assert::assertTrue($ref->isInterface());
        Assert::assertFalse($ref->isInternal()); // userland interface
    }

    #[Test]
    public function get_user_identifier_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(UserInterface::class, 'getUserIdentifier');
        Assert::assertTrue($method->isPublic());

        // no parameters
        Assert::assertCount(0, $method->getParameters());

        $this->assertReturnType($method, 'string', false);
    }

    #[Test]
    public function get_roles_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(UserInterface::class, 'getRoles');
        Assert::assertTrue($method->isPublic());

        // no parameters
        Assert::assertCount(0, $method->getParameters());

        $this->assertReturnType($method, 'array', false);
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
