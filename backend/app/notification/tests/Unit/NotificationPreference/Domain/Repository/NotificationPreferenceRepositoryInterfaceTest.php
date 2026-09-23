<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\NotificationPreference\Domain\Repository;

use App\Notification\NotificationPreference\Domain\Model\NotificationPreference;
use App\Notification\NotificationPreference\Domain\Repository\NotificationPreferenceRepositoryInterface;
use App\Notification\NotificationPreference\Infrastructure\Persistence\NotificationPreferenceRepository;
use App\Shared\Domain\ValueObject\Id;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NotificationPreferenceRepositoryInterfaceTest extends TestCase
{
    #[Test]
    public function interface_is_defined_and_public(): void
    {
        // Arrange
        $interfaceName = NotificationPreferenceRepositoryInterface::class;

        // Act
        $reflection = new \ReflectionClass($interfaceName);

        // Assert
        Assert::assertTrue(\interface_exists($interfaceName));
        Assert::assertTrue($reflection->isInterface());
        Assert::assertFalse($reflection->isInternal());
    }

    #[Test]
    public function save_signature_is_correct(): void
    {
        // Arrange
        $method = new \ReflectionMethod(NotificationPreferenceRepositoryInterface::class, 'save');

        // Act
        $parameters = $method->getParameters();
        $returnType = $method->getReturnType();

        // Assert
        Assert::assertTrue($method->isPublic());
        Assert::assertCount(1, $parameters);
        $this->assertParameterType($parameters[0], NotificationPreference::class, false);
        $this->assertNamedType($returnType, 'void', false);
    }

    #[Test]
    public function update_signature_is_correct(): void
    {
        // Arrange
        $method = new \ReflectionMethod(NotificationPreferenceRepositoryInterface::class, 'update');

        // Act
        $parameters = $method->getParameters();
        $returnType = $method->getReturnType();

        // Assert
        Assert::assertTrue($method->isPublic());
        Assert::assertCount(1, $parameters);
        $this->assertParameterType($parameters[0], NotificationPreference::class, false);
        $this->assertNamedType($returnType, 'void', false);
    }

    #[Test]
    public function find_by_id_signature_is_correct(): void
    {
        // Arrange
        $method = new \ReflectionMethod(NotificationPreferenceRepositoryInterface::class, 'findById');

        // Act
        $parameters = $method->getParameters();
        $returnType = $method->getReturnType();

        // Assert
        Assert::assertTrue($method->isPublic());
        Assert::assertCount(1, $parameters);
        $this->assertParameterType($parameters[0], Id::class, false);
        $this->assertNamedType($returnType, NotificationPreference::class, true);
    }

    #[Test]
    public function find_by_user_id_and_type_signature_is_correct(): void
    {
        // Arrange
        $method = new \ReflectionMethod(NotificationPreferenceRepositoryInterface::class, 'findByUserIdAndType');

        // Act
        $parameters = $method->getParameters();
        $returnType = $method->getReturnType();

        // Assert
        Assert::assertTrue($method->isPublic());
        Assert::assertCount(2, $parameters);
        $this->assertParameterType($parameters[0], Id::class, false);
        $this->assertParameterType($parameters[1], 'string', false);
        $this->assertNamedType($returnType, NotificationPreference::class, true);
    }

    #[Test]
    public function find_by_user_id_signature_and_array_contract_are_correct(): void
    {
        // Arrange
        $method = new \ReflectionMethod(NotificationPreferenceRepositoryInterface::class, 'findByUserId');

        // Act
        $parameters = $method->getParameters();
        $returnType = $method->getReturnType();
        $docBlock = (string) $method->getDocComment();

        // Assert
        Assert::assertTrue($method->isPublic());
        Assert::assertCount(1, $parameters);
        $this->assertParameterType($parameters[0], Id::class, false);
        $this->assertNamedType($returnType, 'array', false);
        Assert::assertNotSame('', $docBlock);
        Assert::assertStringContainsString('@return NotificationPreference[]', $docBlock);
    }

    #[Test]
    public function doctrine_implementation_complies_with_interface_contract(): void
    {
        // Arrange
        $methods = ['save', 'update', 'findById', 'findByUserIdAndType', 'findByUserId'];
        $reflection = new \ReflectionClass(NotificationPreferenceRepository::class);

        // Act
        $implementsInterface = $reflection->implementsInterface(NotificationPreferenceRepositoryInterface::class);

        // Assert
        Assert::assertTrue($implementsInterface);
        foreach ($methods as $methodName) {
            Assert::assertTrue($reflection->hasMethod($methodName), \sprintf('Implementation missing method %s', $methodName));
        }
    }

    private function assertParameterType(\ReflectionParameter $parameter, string $expectedType, bool $allowsNull): void
    {
        $type = $parameter->getType();
        $this->assertNamedType($type, $expectedType, $allowsNull);
    }

    private function assertNamedType(?\ReflectionType $type, string $expectedType, bool $allowsNull): void
    {
        Assert::assertInstanceOf(\ReflectionNamedType::class, $type);
        Assert::assertSame($expectedType, $type->getName());
        Assert::assertSame($allowsNull, $type->allowsNull());
    }
}
