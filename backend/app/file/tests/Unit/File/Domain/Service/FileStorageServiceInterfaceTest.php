<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Domain\Service;

use App\File\File\Domain\Service\FileStorageServiceInterface;
use App\File\File\Domain\ValueObject\FileId;
use App\File\File\Domain\ValueObject\FileName;
use App\File\File\Domain\ValueObject\MimeType;
use App\File\File\Infrastructure\Service\FileStorageService;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileStorageServiceInterfaceTest extends TestCase
{
    #[Test]
    public function interface_is_defined_and_public(): void
    {
        // Arrange
        // Act
        $ref = new \ReflectionClass(FileStorageServiceInterface::class);

        // Assert
        Assert::assertTrue(\interface_exists(FileStorageServiceInterface::class));
        Assert::assertTrue($ref->isInterface());
        Assert::assertFalse($ref->isInternal());
    }

    #[Test]
    public function interface_declares_expected_public_api_surface(): void
    {
        // Arrange
        $expectedMethods = [
            'store',
            'getThumbnailUrl',
            'getFileUrl',
            'delete',
            'getContent',
            'getStream',
            'createThumbnail',
            'getThumbnailContent',
            'getThumbnailStream',
        ];

        // Act
        $ref = new \ReflectionClass(FileStorageServiceInterface::class);
        $actualMethods = \array_map(static fn (\ReflectionMethod $method): string => $method->getName(), $ref->getMethods());

        // Assert
        Assert::assertSame($expectedMethods, $actualMethods);
        Assert::assertCount(9, $actualMethods);
    }

    #[Test]
    public function store_signature_requires_uploaded_file_and_value_objects_and_returns_path(): void
    {
        // Arrange
        $method = new \ReflectionMethod(FileStorageServiceInterface::class, 'store');

        // Act
        $params = $method->getParameters();

        // Assert
        Assert::assertTrue($method->isPublic());
        Assert::assertCount(3, $params);
        $this->assertParamType($params[0], UploadedFile::class, false);
        $this->assertParamType($params[1], FileId::class, false);
        $this->assertParamType($params[2], FileName::class, false);
        $this->assertReturnType($method, 'string', false);
        $this->assertDocblockContains($method, '@throws FileStorageException');
    }

    #[Test]
    public function stream_contracts_use_mixed_return_types_with_resource_docblocks(): void
    {
        // Arrange
        $getStream = new \ReflectionMethod(FileStorageServiceInterface::class, 'getStream');
        $getThumbnailStream = new \ReflectionMethod(FileStorageServiceInterface::class, 'getThumbnailStream');

        // Act
        $getStreamParams = $getStream->getParameters();
        $getThumbnailStreamParams = $getThumbnailStream->getParameters();

        // Assert
        Assert::assertCount(1, $getStreamParams);
        $this->assertParamType($getStreamParams[0], 'string', false);
        $this->assertReturnType($getStream, 'mixed', true);
        $this->assertDocblockContains($getStream, '@return resource');
        $this->assertDocblockContains($getStream, '@throws FileStorageException');

        Assert::assertCount(1, $getThumbnailStreamParams);
        $this->assertParamType($getThumbnailStreamParams[0], 'string', false);
        $this->assertReturnType($getThumbnailStream, 'mixed', true);
        $this->assertDocblockContains($getThumbnailStream, '@return resource|null');
    }

    #[Test]
    public function thumbnail_and_content_methods_keep_nullable_contracts_explicit(): void
    {
        // Arrange
        $getThumbnailUrl = new \ReflectionMethod(FileStorageServiceInterface::class, 'getThumbnailUrl');
        $createThumbnail = new \ReflectionMethod(FileStorageServiceInterface::class, 'createThumbnail');
        $getThumbnailContent = new \ReflectionMethod(FileStorageServiceInterface::class, 'getThumbnailContent');
        $getFileUrl = new \ReflectionMethod(FileStorageServiceInterface::class, 'getFileUrl');
        $getContent = new \ReflectionMethod(FileStorageServiceInterface::class, 'getContent');
        $delete = new \ReflectionMethod(FileStorageServiceInterface::class, 'delete');

        // Act
        $createThumbnailParams = $createThumbnail->getParameters();

        // Assert
        $this->assertReturnType($getThumbnailUrl, 'string', true);
        $this->assertReturnType($createThumbnail, 'string', true);
        Assert::assertCount(2, $createThumbnailParams);
        $this->assertParamType($createThumbnailParams[0], 'string', false);
        $this->assertParamType($createThumbnailParams[1], MimeType::class, false);
        $this->assertReturnType($getThumbnailContent, 'string', true);

        $this->assertReturnType($getFileUrl, 'string', false);
        $this->assertReturnType($getContent, 'string', false);
        $this->assertReturnType($delete, 'void', false);
    }

    #[Test]
    public function concrete_file_storage_services_implement_interface_contract(): void
    {
        // Arrange
        $implementations = [
            FileStorageService::class,
        ];

        // Act
        $checks = \array_map(
            static fn (string $class): bool => \is_subclass_of($class, FileStorageServiceInterface::class),
            $implementations,
        );

        // Assert
        Assert::assertCount(1, $checks);
        Assert::assertSame([true], $checks);
    }

    private function assertParamType(\ReflectionParameter $param, string $expectedType, bool $allowsNull): void
    {
        $type = $param->getType();
        Assert::assertInstanceOf(\ReflectionNamedType::class, $type);
        Assert::assertSame($expectedType, $type->getName());
        Assert::assertSame($allowsNull, $type->allowsNull());
    }

    private function assertReturnType(\ReflectionMethod $method, string $expectedType, bool $allowsNull): void
    {
        $type = $method->getReturnType();
        Assert::assertInstanceOf(\ReflectionNamedType::class, $type);
        Assert::assertSame($expectedType, $type->getName());
        Assert::assertSame($allowsNull, $type->allowsNull());
    }

    private function assertDocblockContains(\ReflectionMethod $method, string $expected): void
    {
        $doc = (string) $method->getDocComment();
        Assert::assertNotSame('', $doc);
        Assert::assertStringContainsString($expected, $doc);
    }
}
