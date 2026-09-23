<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Notification\Domain\Repository;

use App\Notification\Notification\Domain\Model\Notification;
use App\Notification\Notification\Domain\Repository\NotificationRepositoryInterface;
use App\Notification\Notification\Infrastructure\Persistence\NotificationRepository;
use App\Shared\Domain\ValueObject\Id;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NotificationRepositoryInterfaceTest extends TestCase
{
    #[Test]
    public function interface_is_defined_and_public(): void
    {
        Assert::assertTrue(\interface_exists(NotificationRepositoryInterface::class));

        $ref = new \ReflectionClass(NotificationRepositoryInterface::class);
        Assert::assertTrue($ref->isInterface());
        Assert::assertFalse($ref->isInternal());
    }

    #[Test]
    public function save_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(NotificationRepositoryInterface::class, 'save');
        Assert::assertTrue($method->isPublic());

        $params = $method->getParameters();
        Assert::assertCount(1, $params);
        $this->assertParamType($params[0], Notification::class, false);

        $this->assertReturnType($method, 'void', false);
    }

    #[Test]
    public function update_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(NotificationRepositoryInterface::class, 'update');
        Assert::assertTrue($method->isPublic());

        $params = $method->getParameters();
        Assert::assertCount(1, $params);
        $this->assertParamType($params[0], Notification::class, false);

        $this->assertReturnType($method, 'void', false);
    }

    #[Test]
    public function find_by_id_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(NotificationRepositoryInterface::class, 'findById');
        Assert::assertTrue($method->isPublic());

        $params = $method->getParameters();
        Assert::assertCount(1, $params);
        $this->assertParamType($params[0], Id::class, false);

        $this->assertReturnType($method, Notification::class, true);
    }

    #[Test]
    public function find_pending_by_type_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(NotificationRepositoryInterface::class, 'findPendingByType');
        Assert::assertTrue($method->isPublic());

        $params = $method->getParameters();
        Assert::assertCount(1, $params);
        $this->assertParamType($params[0], 'string', false);

        $this->assertReturnType($method, 'array', false);
        $this->assertDocblockContains($method, '@return Notification[]');
    }

    #[Test]
    public function doctrine_implementation_complies_with_interface(): void
    {
        $ref = new \ReflectionClass(NotificationRepository::class);
        Assert::assertTrue($ref->implementsInterface(NotificationRepositoryInterface::class));

        foreach (['save', 'update', 'findById', 'findPendingByType'] as $method) {
            Assert::assertTrue(
                $ref->hasMethod($method),
                \sprintf('Implementation missing method %s', $method),
            );
        }
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

    private function assertDocblockContains(\ReflectionMethod $method, string $expected): void
    {
        $doc = (string) $method->getDocComment();
        Assert::assertNotSame('', $doc, \sprintf('%s should have a docblock', $method->getName()));
        Assert::assertStringContainsString($expected, $doc);
    }
}
