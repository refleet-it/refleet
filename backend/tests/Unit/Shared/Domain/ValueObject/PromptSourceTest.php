<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\PromptSource;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PromptSource::class)]
final class PromptSourceTest extends TestCase
{
    #[Test]
    public function round_trips_through_its_array_form(): void
    {
        $source = PromptSource::fromArray(['id' => 'builtin:git-conventions', 'name' => 'Git conventions', 'kind' => 'rule', 'builtIn' => true]);

        Assert::assertSame('builtin:git-conventions', $source->id());
        Assert::assertSame('Git conventions', $source->name());
        Assert::assertSame('rule', $source->kind());
        Assert::assertTrue($source->builtIn());
        Assert::assertSame(['id' => 'builtin:git-conventions', 'name' => 'Git conventions', 'kind' => 'rule', 'builtIn' => true], $source->toArray());
    }

    #[Test]
    public function built_in_defaults_to_false_when_the_wire_form_omits_it(): void
    {
        Assert::assertFalse(PromptSource::fromArray(['id' => 'x', 'name' => 'Custom', 'kind' => 'task'])->builtIn());
    }

    #[Test]
    public function rejects_an_unknown_kind(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PromptSource('x', 'Custom', 'addon', false);
    }
}
