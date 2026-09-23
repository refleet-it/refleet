<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Domain\Repository;

use App\File\File\Domain\Model\File;
use App\File\File\Domain\Repository\FileRepositoryInterface;
use App\File\File\Domain\ValueObject\FileId;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileRepositoryInterfaceTest extends TestCase
{
    #[Test]
    public function interface_is_defined_and_public(): void
    {
        Assert::assertTrue(\interface_exists(FileRepositoryInterface::class));

        $ref = new \ReflectionClass(FileRepositoryInterface::class);
        Assert::assertTrue($ref->isInterface());
        Assert::assertFalse($ref->isInternal());
    }

    #[Test]
    public function save_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(FileRepositoryInterface::class, 'save');
        Assert::assertTrue($method->isPublic());

        $params = $method->getParameters();
        Assert::assertCount(1, $params);
        $this->assertParamType($params[0], File::class, false);

        $this->assertReturnType($method, 'void', false);
    }

    #[Test]
    public function find_by_id_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(FileRepositoryInterface::class, 'findById');
        Assert::assertTrue($method->isPublic());

        $params = $method->getParameters();
        Assert::assertCount(1, $params);
        $this->assertParamType($params[0], FileId::class, false);

        $this->assertReturnType($method, File::class, true);
    }

    #[Test]
    public function find_by_uploader_id_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(FileRepositoryInterface::class, 'findByUploaderId');
        Assert::assertTrue($method->isPublic());

        $params = $method->getParameters();
        Assert::assertCount(1, $params);
        $this->assertParamType($params[0], UserId::class, false);

        $this->assertReturnType($method, 'array', false);
        $this->assertDocblockContains($method, '@return File[]');
    }

    #[Test]
    public function find_by_tenant_id_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(FileRepositoryInterface::class, 'findByTenantId');
        Assert::assertTrue($method->isPublic());

        $params = $method->getParameters();
        Assert::assertCount(1, $params);
        $this->assertParamType($params[0], TenantId::class, false);

        $this->assertReturnType($method, 'array', false);
        $this->assertDocblockContains($method, '@return File[]');
    }

    #[Test]
    public function find_active_by_uploader_id_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(FileRepositoryInterface::class, 'findActiveByUploaderId');
        Assert::assertTrue($method->isPublic());

        $params = $method->getParameters();
        Assert::assertCount(1, $params);
        $this->assertParamType($params[0], UserId::class, false);

        $this->assertReturnType($method, 'array', false);
        $this->assertDocblockContains($method, '@return File[]');
    }

    #[Test]
    public function find_active_by_tenant_id_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(FileRepositoryInterface::class, 'findActiveByTenantId');
        Assert::assertTrue($method->isPublic());

        $params = $method->getParameters();
        Assert::assertCount(1, $params);
        $this->assertParamType($params[0], TenantId::class, false);

        $this->assertReturnType($method, 'array', false);
        $this->assertDocblockContains($method, '@return File[]');
    }

    #[Test]
    public function count_by_uploader_id_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(FileRepositoryInterface::class, 'countByUploaderId');
        Assert::assertTrue($method->isPublic());

        $params = $method->getParameters();
        Assert::assertCount(1, $params);
        $this->assertParamType($params[0], UserId::class, false);

        $this->assertReturnType($method, 'int', false);
    }

    #[Test]
    public function count_by_tenant_id_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(FileRepositoryInterface::class, 'countByTenantId');
        Assert::assertTrue($method->isPublic());

        $params = $method->getParameters();
        Assert::assertCount(1, $params);
        $this->assertParamType($params[0], TenantId::class, false);

        $this->assertReturnType($method, 'int', false);
    }

    #[Test]
    public function delete_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(FileRepositoryInterface::class, 'delete');
        Assert::assertTrue($method->isPublic());

        $params = $method->getParameters();
        Assert::assertCount(1, $params);
        $this->assertParamType($params[0], FileId::class, false);

        $this->assertReturnType($method, 'void', false);
    }

    #[Test]
    public function exists_signature_is_correct(): void
    {
        $method = new \ReflectionMethod(FileRepositoryInterface::class, 'exists');
        Assert::assertTrue($method->isPublic());

        $params = $method->getParameters();
        Assert::assertCount(1, $params);
        $this->assertParamType($params[0], FileId::class, false);

        $this->assertReturnType($method, 'bool', false);
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
        $docComment = (string) $method->getDocComment();
        Assert::assertNotSame('', $docComment, \sprintf('%s should have a docblock', $method->getName()));
        Assert::assertStringContainsString($expected, $docComment);
    }
}
