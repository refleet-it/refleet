<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Service;

use App\Identity\Account\Infrastructure\Service\UserEmailProvider;
use App\Shared\Domain\Service\UserEmailProviderInterface;
use App\Shared\Domain\User\UserId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserEmailProviderInterfaceTest extends TestCase
{
    #[Test]
    public function interface_is_defined_as_userland_interface(): void
    {
        // Arrange
        $interface = UserEmailProviderInterface::class;

        // Act
        $reflection = new \ReflectionClass($interface);

        // Assert
        Assert::assertTrue(\interface_exists($interface));
        Assert::assertTrue($reflection->isInterface());
        Assert::assertFalse($reflection->isInternal());
    }

    #[Test]
    public function get_email_by_user_id_signature_is_stable(): void
    {
        // Arrange
        $method = new \ReflectionMethod(UserEmailProviderInterface::class, 'getEmailByUserId');

        // Act
        $parameters = $method->getParameters();
        $parameterType = $parameters[0]->getType();
        $returnType = $method->getReturnType();

        // Assert
        Assert::assertTrue($method->isPublic());
        Assert::assertCount(1, $parameters);
        Assert::assertInstanceOf(\ReflectionNamedType::class, $parameterType);
        Assert::assertSame(UserId::class, $parameterType->getName());
        Assert::assertFalse($parameterType->allowsNull());
        Assert::assertInstanceOf(\ReflectionNamedType::class, $returnType);
        Assert::assertSame('string', $returnType->getName());
        Assert::assertTrue($returnType->allowsNull());
    }

    #[Test]
    public function concrete_service_implements_interface_contract(): void
    {
        // Arrange
        $serviceClass = UserEmailProvider::class;

        // Act
        $implementsContract = \is_subclass_of($serviceClass, UserEmailProviderInterface::class);

        // Assert
        Assert::assertTrue($implementsContract);
    }
}
