<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Repository;

use App\Identity\Account\Domain\Account\Model\PasswordResetToken;
use App\Identity\Account\Domain\Account\Repository\PasswordResetTokenRepositoryInterface;
use App\Identity\Account\Infrastructure\Persistence\DoctrinePasswordResetTokenRepository;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PasswordResetTokenRepositoryInterfaceTest extends TestCase
{
    private const array METHOD_DEFINITIONS = [
        'save' => [
            'parameters' => [
                [PasswordResetToken::class, false],
            ],
            'return' => ['void', false],
        ],
        'findByToken' => [
            'parameters' => [
                ['string', false],
            ],
            'return' => [PasswordResetToken::class, true],
        ],
        'findByAccountId' => [
            'parameters' => [
                ['string', false],
            ],
            'return' => [PasswordResetToken::class, true],
        ],
        'delete' => [
            'parameters' => [
                [PasswordResetToken::class, false],
            ],
            'return' => ['void', false],
        ],
    ];

    #[Test]
    public function interface_is_defined_and_public(): void
    {
        Assert::assertTrue(\interface_exists(PasswordResetTokenRepositoryInterface::class));

        $ref = new \ReflectionClass(PasswordResetTokenRepositoryInterface::class);
        Assert::assertTrue($ref->isInterface());
        Assert::assertFalse($ref->isInternal());
    }

    #[Test]
    public function interface_declares_expected_methods_only(): void
    {
        $ref = new \ReflectionClass(PasswordResetTokenRepositoryInterface::class);

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
    ): void {
        $method = new \ReflectionMethod(PasswordResetTokenRepositoryInterface::class, $methodName);
        Assert::assertTrue($method->isPublic());

        $actualParameters = $method->getParameters();
        Assert::assertCount(\count($parameters), $actualParameters);

        foreach ($parameters as $index => [$expectedType, $allowsNull]) {
            $this->assertParamType($actualParameters[$index], $expectedType, $allowsNull);
        }

        [$expectedReturnType, $allowsNullReturn] = $returnType;
        $this->assertReturnType($method, $expectedReturnType, $allowsNullReturn);
    }

    #[Test]
    public function doctrine_repository_conforms_to_contract(): void
    {
        $ref = new \ReflectionClass(DoctrinePasswordResetTokenRepository::class);
        Assert::assertTrue($ref->isFinal(), 'DoctrinePasswordResetTokenRepository should be final');
        Assert::assertTrue($ref->isReadOnly(), 'DoctrinePasswordResetTokenRepository should be readonly');
        Assert::assertTrue($ref->implementsInterface(PasswordResetTokenRepositoryInterface::class));

        foreach (\array_keys(self::METHOD_DEFINITIONS) as $methodName) {
            Assert::assertTrue($ref->hasMethod($methodName), \sprintf('Missing method %s', $methodName));

            $method = $ref->getMethod($methodName);
            Assert::assertTrue($method->isPublic(), \sprintf('Method %s must be public', $methodName));

            $overrideAttributes = $method->getAttributes(\Override::class);
            Assert::assertCount(1, $overrideAttributes, \sprintf('Method %s should declare #[Override]', $methodName));
        }
    }

    public static function methodSignatureProvider(): iterable
    {
        foreach (self::METHOD_DEFINITIONS as $methodName => $definition) {
            yield $methodName => [
                $methodName,
                $definition['parameters'],
                $definition['return'],
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
}
