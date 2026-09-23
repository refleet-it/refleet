<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Query;

use App\Shared\Application\Query\QueryInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueryInterfaceTest extends TestCase
{
    #[Test]
    public function interface_is_defined_and_public(): void
    {
        Assert::assertTrue(\interface_exists(QueryInterface::class));

        $ref = new \ReflectionClass(QueryInterface::class);
        Assert::assertTrue($ref->isInterface());
        Assert::assertFalse($ref->isInternal()); // userland interface
    }

    #[Test]
    public function interface_has_no_methods(): void
    {
        $ref = new \ReflectionClass(QueryInterface::class);
        Assert::assertCount(0, $ref->getMethods());
    }
}
