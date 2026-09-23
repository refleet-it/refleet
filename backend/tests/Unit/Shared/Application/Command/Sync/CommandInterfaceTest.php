<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Command\Sync;

use App\File\File\Application\Command\UploadFile\UploadFileCommand;
use App\Shared\Application\Command\Sync\CommandInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CommandInterfaceTest extends TestCase
{
    #[Test]
    public function interface_is_defined_and_public(): void
    {
        Assert::assertTrue(\interface_exists(CommandInterface::class));

        $ref = new \ReflectionClass(CommandInterface::class);
        Assert::assertTrue($ref->isInterface());
        Assert::assertFalse($ref->isInternal()); // userland interface
    }

    #[Test]
    public function interface_has_no_methods(): void
    {
        $ref = new \ReflectionClass(CommandInterface::class);
        Assert::assertCount(0, $ref->getMethods());
    }

    #[Test]
    public function some_commands_implement_the_interface(): void
    {
        $implementations = [
            UploadFileCommand::class,
        ];

        foreach ($implementations as $class) {
            Assert::assertTrue(\is_subclass_of($class, CommandInterface::class));
        }
    }
}
