<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Exception;

use App\Shared\Domain\Exception\AppException;
use App\Shared\Domain\Exception\DomainException;
use App\Tests\Helpers\Shared\Domain\Exception\FakeDomainException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DomainException::class)]
#[UsesClass(AppException::class)]
final class DomainExceptionTest extends TestCase
{
    #[Test]
    public function should_be_abstract_and_extend_app_exception(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(DomainException::class);
        $exception = new FakeDomainException('Domain failure', 123);

        // Act
        $isAbstract = $reflection->isAbstract();

        // Assert
        Assert::assertTrue($isAbstract);
        Assert::assertInstanceOf(AppException::class, $exception);
    }

    #[Test]
    public function should_preserve_message_and_code_from_constructor_arguments(): void
    {
        // Arrange
        $message = '';
        $code = -7;

        // Act
        $exception = new FakeDomainException($message, $code);

        // Assert
        Assert::assertSame($message, $exception->getMessage());
        Assert::assertSame($code, $exception->getCode());
    }
}
