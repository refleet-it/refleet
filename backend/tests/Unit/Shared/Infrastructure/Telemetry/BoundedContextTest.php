<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Telemetry;

use App\Shared\Infrastructure\Telemetry\BoundedContext;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BoundedContext::class)]
final class BoundedContextTest extends TestCase
{
    #[Test]
    public function extracts_context_from_app_class_name(): void
    {
        Assert::assertSame(
            'business',
            BoundedContext::fromClassName('App\Business\Tables\Infrastructure\Api\GetTablesController'),
        );
    }

    #[Test]
    public function extracts_context_from_handler_name_with_method(): void
    {
        Assert::assertSame(
            'payments',
            BoundedContext::fromClassName('App\Payments\Payment\Application\Command\SyncBusinessHandler::__invoke'),
        );
    }

    #[Test]
    public function returns_null_for_non_app_class_name(): void
    {
        Assert::assertNull(BoundedContext::fromClassName('Acme\Contracts\Message\BusinessSync\BusinessSyncMessage'));
        Assert::assertNull(BoundedContext::fromClassName(\Symfony\Component\HttpKernel\HttpKernel::class));
        Assert::assertNull(BoundedContext::fromClassName('App'));
    }
}
