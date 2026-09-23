<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Query;

use App\Shared\Application\Query\CursorListQueryInterface;
use App\Shared\Domain\ValueObject\CursorListParameters;
use App\Shared\Domain\ValueObject\CursorListResponse;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CursorListQueryInterfaceTest extends TestCase
{
    private const array METHOD_DEFINITIONS = [
        'getList' => [
            'parameters' => [
                [CursorListParameters::class, false],
            ],
            'return' => [CursorListResponse::class, false],
            'doc' => ['@return CursorListResponse<T>'],
        ],
        'getAll' => [
            'parameters' => [
                [CursorListParameters::class, false],
            ],
            'return' => ['array', false],
            'doc' => ['@return T[]'],
        ],
    ];

    #[Test]
    public function interface_is_defined_and_public(): void
    {
        Assert::assertTrue(\interface_exists(CursorListQueryInterface::class));

        $ref = new \ReflectionClass(CursorListQueryInterface::class);
        Assert::assertTrue($ref->isInterface());
        Assert::assertFalse($ref->isInternal()); // userland interface

        $this->assertClassDocblockContains($ref, '@template T');
    }

    #[Test]
    #[DataProvider('methodSignatureProvider')]
    public function method_signature_is_correct(
        string $methodName,
        array $parameters,
        array $returnType,
        array $docExpectations,
    ): void {
        $method = new \ReflectionMethod(CursorListQueryInterface::class, $methodName);
        Assert::assertTrue($method->isPublic());

        $actualParameters = $method->getParameters();
        Assert::assertCount(\count($parameters), $actualParameters);
        foreach ($parameters as $index => [$expectedType, $allowsNull]) {
            $this->assertParamType($actualParameters[$index], $expectedType, $allowsNull);
        }

        [$expectedReturn, $allowsNullReturn] = $returnType;
        $this->assertReturnType($method, $expectedReturn, $allowsNullReturn);

        foreach ($docExpectations as $expectedDocLine) {
            $this->assertDocblockContains($method, $expectedDocLine);
        }
    }

    public static function methodSignatureProvider(): iterable
    {
        foreach (self::METHOD_DEFINITIONS as $methodName => $definition) {
            yield $methodName => [
                $methodName,
                $definition['parameters'],
                $definition['return'],
                $definition['doc'],
            ];
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

    private function assertClassDocblockContains(\ReflectionClass $class, string $expected): void
    {
        $doc = (string) $class->getDocComment();
        Assert::assertNotSame('', $doc, 'Interface should declare template via docblock');
        Assert::assertStringContainsString($expected, $doc);
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
