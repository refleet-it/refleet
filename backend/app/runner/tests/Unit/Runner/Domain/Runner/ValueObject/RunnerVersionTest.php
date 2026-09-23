<?php

declare(strict_types=1);

namespace App\Tests\Unit\Runner\Runner\Domain\Runner\ValueObject;

use App\Runner\Runner\Domain\Runner\ValueObject\RunnerVersion;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RunnerVersion::class)]
final class RunnerVersionTest extends TestCase
{
    #[Test]
    public function compares_numerically_per_segment(): void
    {
        $older = RunnerVersion::tryFromString('0.1.9');
        $newer = RunnerVersion::tryFromString('v0.1.186');

        Assert::assertNotNull($older);
        Assert::assertNotNull($newer);
        Assert::assertTrue($older->isOlderThan($newer));
        Assert::assertFalse($newer->isOlderThan($older));
        Assert::assertFalse($older->isOlderThan($older));
    }

    #[Test]
    public function rejects_anything_that_is_not_three_numbers(): void
    {
        Assert::assertNull(RunnerVersion::tryFromString('latest'));
        Assert::assertNull(RunnerVersion::tryFromString('0.1'));
        Assert::assertNull(RunnerVersion::tryFromString(''));
    }
}
