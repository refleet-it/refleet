<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Domain\Shift\ValueObject;

use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use App\Shared\Domain\ValueObject\PromptSource;
use App\Shift\Shift\Domain\Shift\Exception\InvalidCriteriaException;
use App\Shift\Shift\Domain\Shift\ValueObject\ChangeCriteria;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChangeCriteria::class)]
final class ChangeCriteriaTest extends TestCase
{
    #[Test]
    public function builds_ai_criteria(): void
    {
        $criteria = ChangeCriteria::ai('Remove the acme/legacy-lib dependency');

        Assert::assertSame(CriteriaModeEnum::AI, $criteria->mode());
        Assert::assertSame('Remove the acme/legacy-lib dependency', $criteria->prompt());
        Assert::assertNull($criteria->model());
        Assert::assertNull($criteria->engine());
    }

    #[Test]
    public function builds_ai_criteria_with_a_model_override(): void
    {
        $criteria = ChangeCriteria::ai('Remove the acme/legacy-lib dependency', 'claude-haiku-4-5-20251001');

        Assert::assertSame('claude-haiku-4-5-20251001', $criteria->model());
    }

    #[Test]
    public function builds_ai_criteria_with_a_kiro_engine(): void
    {
        $criteria = ChangeCriteria::ai('Remove the acme/legacy-lib dependency', null, CriteriaEngineEnum::KIRO);

        Assert::assertSame(CriteriaEngineEnum::KIRO, $criteria->engine());
    }

    #[Test]
    public function keeps_rules_and_their_sources(): void
    {
        $source = new PromptSource('builtin:git-conventions', 'Git conventions', 'rule', true);
        $criteria = ChangeCriteria::ai('Remove the dependency', rules: 'Conventional Commits.', sources: [5 => $source]);

        Assert::assertSame('Conventional Commits.', $criteria->rules());
        Assert::assertSame([$source], $criteria->sources());
    }

    #[Test]
    public function blank_rules_collapse_to_null(): void
    {
        Assert::assertNull(ChangeCriteria::ai('Remove the dependency', rules: "  \n")->rules());
        Assert::assertSame([], ChangeCriteria::ai('Remove the dependency')->sources());
    }

    #[Test]
    public function rejects_ai_criteria_with_empty_prompt(): void
    {
        $this->expectException(InvalidCriteriaException::class);

        ChangeCriteria::ai('   ');
    }
}
