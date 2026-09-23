<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Domain\Service;

use App\File\File\Domain\Service\WebPImageStorageServiceInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WebPImageStorageServiceInterfaceTest extends TestCase
{
    #[Test]
    public function interface_is_defined_and_public(): void
    {
        // Arrange
        // Act
        $ref = new \ReflectionClass(WebPImageStorageServiceInterface::class);

        // Assert
        Assert::assertTrue(\interface_exists(WebPImageStorageServiceInterface::class));
        Assert::assertTrue($ref->isInterface());
        Assert::assertFalse($ref->isInternal());
    }

    #[Test]
    public function interface_declares_expected_public_api_surface(): void
    {
        // Arrange
        $expectedMethods = [
            'convertToWebP',
            'createWebPThumbnail',
            'delete',
        ];

        // Act
        $ref = new \ReflectionClass(WebPImageStorageServiceInterface::class);
        $actualMethods = \array_map(static fn (\ReflectionMethod $method): string => $method->getName(), $ref->getMethods());

        // Assert
        Assert::assertSame($expectedMethods, $actualMethods);
        Assert::assertCount(3, $actualMethods);
    }

    #[Test]
    public function conversion_methods_require_non_nullable_inputs_and_return_nullable_path(): void
    {
        // Arrange
        $convertToWebP = new \ReflectionMethod(WebPImageStorageServiceInterface::class, 'convertToWebP');
        $createWebPThumbnail = new \ReflectionMethod(WebPImageStorageServiceInterface::class, 'createWebPThumbnail');

        // Act
        $convertToWebPParams = $convertToWebP->getParameters();
        $createWebPThumbnailParams = $createWebPThumbnail->getParameters();

        // Assert
        Assert::assertTrue($convertToWebP->isPublic());
        Assert::assertCount(2, $convertToWebPParams);
        $this->assertParamType($convertToWebPParams[0], 'string', false);
        $this->assertParamType($convertToWebPParams[1], 'string', false);
        $this->assertReturnType($convertToWebP, 'string', true);

        Assert::assertTrue($createWebPThumbnail->isPublic());
        Assert::assertCount(2, $createWebPThumbnailParams);
        $this->assertParamType($createWebPThumbnailParams[0], 'string', false);
        $this->assertParamType($createWebPThumbnailParams[1], 'string', false);
        $this->assertReturnType($createWebPThumbnail, 'string', true);
    }

    #[Test]
    public function delete_signature_requires_non_nullable_path_and_returns_void(): void
    {
        // Arrange
        $method = new \ReflectionMethod(WebPImageStorageServiceInterface::class, 'delete');

        // Act
        $params = $method->getParameters();

        // Assert
        Assert::assertTrue($method->isPublic());
        Assert::assertCount(1, $params);
        $this->assertParamType($params[0], 'string', false);
        $this->assertReturnType($method, 'void', false);
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
}
