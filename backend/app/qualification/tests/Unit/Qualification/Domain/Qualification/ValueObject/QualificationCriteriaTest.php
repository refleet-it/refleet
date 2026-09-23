<?php

declare(strict_types=1);

namespace App\Tests\Unit\Qualification\Qualification\Domain\Qualification\ValueObject;

use App\Qualification\Qualification\Domain\Qualification\Exception\InvalidCriteriaException;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationCriteria;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use App\Shared\Domain\ValueObject\PromptSource;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(QualificationCriteria::class)]
final class QualificationCriteriaTest extends TestCase
{
    #[Test]
    public function builds_ai_criteria(): void
    {
        $criteria = QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?');

        Assert::assertSame(CriteriaModeEnum::AI, $criteria->mode());
        Assert::assertSame('Does this repository depend on acme/legacy-lib?', $criteria->prompt());
        Assert::assertNull($criteria->model());
        Assert::assertNull($criteria->engine());
    }

    #[Test]
    public function builds_ai_criteria_with_a_model_override(): void
    {
        $criteria = QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?', 'claude-opus-5');

        Assert::assertSame('claude-opus-5', $criteria->model());
    }

    #[Test]
    public function builds_ai_criteria_with_a_kiro_engine(): void
    {
        $criteria = QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?', null, CriteriaEngineEnum::KIRO);

        Assert::assertSame(CriteriaEngineEnum::KIRO, $criteria->engine());
    }

    #[Test]
    public function keeps_rules_and_their_sources(): void
    {
        $source = new PromptSource('builtin:evidence-based-scoring', 'Evidence-based scoring', 'rule', true);
        $criteria = QualificationCriteria::ai('Uses Symfony < 6', rules: 'Cite paths.', sources: [3 => $source]);

        Assert::assertSame('Cite paths.', $criteria->rules());
        Assert::assertSame([$source], $criteria->sources());
    }

    #[Test]
    public function blank_rules_collapse_to_null(): void
    {
        Assert::assertNull(QualificationCriteria::ai('Uses Symfony < 6', rules: '  ')->rules());
        Assert::assertSame([], QualificationCriteria::ai('Uses Symfony < 6')->sources());
    }

    #[Test]
    public function rejects_ai_criteria_with_empty_prompt(): void
    {
        $this->expectException(InvalidCriteriaException::class);

        QualificationCriteria::ai('   ');
    }
}
