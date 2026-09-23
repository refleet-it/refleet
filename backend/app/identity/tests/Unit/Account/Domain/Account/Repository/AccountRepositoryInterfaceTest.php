<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Repository;

use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Infrastructure\Persistence\DoctrineAccountRepository;
use App\Shared\Domain\ValueObject\CursorListParameters;
use App\Shared\Domain\ValueObject\CursorListResponse;
use App\Shared\Domain\ValueObject\Id;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AccountRepositoryInterfaceTest extends TestCase
{
    private const array METHOD_DEFINITIONS = [
        'save' => [
            'parameters' => [
                [Account::class, false],
            ],
            'return' => ['void', false],
            'doc' => [],
        ],
        'findById' => [
            'parameters' => [
                [Id::class, false],
            ],
            'return' => [Account::class, true],
            'doc' => [],
        ],
        'findByEmail' => [
            'parameters' => [
                ['string', false],
            ],
            'return' => [Account::class, true],
            'doc' => [],
        ],
        'update' => [
            'parameters' => [
                [Account::class, false],
            ],
            'return' => ['void', false],
            'doc' => [],
        ],
        'getPaginatedList' => [
            'parameters' => [
                [CursorListParameters::class, false],
            ],
            'return' => [CursorListResponse::class, false],
            'doc' => ['@return CursorListResponse<Account>'],
        ],
    ];

    #[Test]
    public function interface_is_defined_and_public(): void
    {
        Assert::assertTrue(\interface_exists(AccountRepositoryInterface::class));

        $ref = new \ReflectionClass(AccountRepositoryInterface::class);
        Assert::assertTrue($ref->isInterface());
        Assert::assertFalse($ref->isInternal());
    }

    #[Test]
    public function interface_declares_expected_methods_only(): void
    {
        $ref = new \ReflectionClass(AccountRepositoryInterface::class);

        $methods = $ref->getMethods();
        Assert::assertCount(\count(self::METHOD_DEFINITIONS), $methods);

        $expectedNames = \array_keys(self::METHOD_DEFINITIONS);
        \sort($expectedNames);

        $actualNames = \array_map(static fn (\ReflectionMethod $method) => $method->getName(), $methods);
        \sort($actualNames);

        Assert::assertSame($expectedNames, $actualNames);
    }

    #[Test]
    #[DataProvider('methodSignatureProvider')]
    public function method_signature_is_correct(
        string $methodName,
        array $parameters,
        array $returnType,
        array $docExpectations,
    ): void {
        $method = new \ReflectionMethod(AccountRepositoryInterface::class, $methodName);
        Assert::assertTrue($method->isPublic());

        $actualParameters = $method->getParameters();
        Assert::assertCount(\count($parameters), $actualParameters);

        foreach ($parameters as $index => [$expectedType, $allowsNull]) {
            $this->assertParamType($actualParameters[$index], $expectedType, $allowsNull);
        }

        [$expectedReturnType, $allowsNullReturn] = $returnType;
        $this->assertReturnType($method, $expectedReturnType, $allowsNullReturn);

        if ([] !== $docExpectations) {
            foreach ($docExpectations as $expected) {
                $this->assertDocblockContains($method, $expected);
            }
        }
    }

    #[Test]
    public function doctrine_repository_conforms_to_contract(): void
    {
        $ref = new \ReflectionClass(DoctrineAccountRepository::class);
        Assert::assertTrue($ref->isFinal(), 'DoctrineAccountRepository should be final');
        Assert::assertTrue($ref->isReadOnly(), 'DoctrineAccountRepository should be readonly');
        Assert::assertTrue($ref->implementsInterface(AccountRepositoryInterface::class));

        foreach (self::METHOD_DEFINITIONS as $methodName => $definition) {
            Assert::assertTrue($ref->hasMethod($methodName), \sprintf('Missing method %s', $methodName));

            $method = $ref->getMethod($methodName);
            Assert::assertTrue($method->isPublic(), \sprintf('Method %s must be public', $methodName));

            $overrideAttributes = $method->getAttributes(\Override::class);
            Assert::assertCount(1, $overrideAttributes, \sprintf('Method %s should declare #[Override]', $methodName));

            $actualParameters = $method->getParameters();
            Assert::assertCount(\count($definition['parameters']), $actualParameters);

            foreach ($definition['parameters'] as $index => [$expectedType, $allowsNull]) {
                $this->assertParamType($actualParameters[$index], $expectedType, $allowsNull);
            }

            [$expectedReturnType, $allowsNullReturn] = $definition['return'];
            $this->assertReturnType($method, $expectedReturnType, $allowsNullReturn);
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
