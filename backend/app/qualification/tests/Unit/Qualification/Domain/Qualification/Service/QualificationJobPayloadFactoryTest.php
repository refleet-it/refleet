<?php

declare(strict_types=1);

namespace App\Tests\Unit\Qualification\Qualification\Domain\Qualification\Service;

use App\Qualification\Qualification\Domain\Qualification\Service\QualificationJobPayloadFactory;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationCriteria;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(QualificationJobPayloadFactory::class)]
final class QualificationJobPayloadFactoryTest extends TestCase
{
    private QualificationJobPayloadFactory $factory;

    #[Test]
    public function builds_a_payload_that_wraps_the_criteria_in_the_agent_instructions(): void
    {
        $payload = $this->factory->build(
            QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?', 'claude-haiku-4-5', CriteriaEngineEnum::KIRO),
            $this->snapshot(),
        );

        Assert::assertSame('ai', $payload['mode']);
        Assert::assertSame('kiro', $payload['engine']);
        Assert::assertSame('claude-haiku-4-5', $payload['model']);
        Assert::assertStringContainsString("## Criteria\n\nDoes this repository depend on acme/legacy-lib?\n", (string) $payload['prompt']);
        Assert::assertSame(
            ['externalId' => '1', 'path' => 'group/project', 'name' => 'Project', 'defaultBranch' => 'main'],
            $payload['project'],
        );
    }

    #[Test]
    public function defaults_the_engine_to_claude(): void
    {
        $payload = $this->factory->build(QualificationCriteria::ai('Uses Symfony < 6'), $this->snapshot());

        Assert::assertSame('claude', $payload['engine']);
        Assert::assertNull($payload['model']);
    }

    #[Test]
    public function the_ai_prompt_demands_a_json_score_on_the_five_point_scale_and_makes_the_checkout_the_source_of_truth(): void
    {
        $prompt = $this->factory->buildAiQualificationPrompt('Uses Symfony < 6');

        Assert::assertStringContainsString('{"score": <integer 1-5>, "reasoning": "<2-3 sentences>"}', $prompt);
        Assert::assertStringContainsString('- 5 — clearly matches', $prompt);
        Assert::assertStringContainsString('- 1 — clearly does not match', $prompt);
        Assert::assertStringContainsString('The JSON object is mandatory', $prompt);
        Assert::assertStringContainsString('recorded as a failed run', $prompt);
        Assert::assertStringContainsString('inconclusive, score 3 or lower', $prompt);
        Assert::assertStringContainsString('checked out in your current working directory', $prompt);
        Assert::assertStringContainsString('Do not create, modify or delete any files', $prompt);
        Assert::assertStringContainsString("do not\nask questions", $prompt);
        Assert::assertStringNotContainsString('QUALIFICATION_DECISION', $prompt);
    }

    #[Test]
    public function trims_the_criteria_before_embedding_them(): void
    {
        $prompt = $this->factory->buildAiQualificationPrompt("\n  Uses Symfony < 6  \n");

        Assert::assertStringContainsString("## Criteria\n\nUses Symfony < 6\n\n## Answer format", $prompt);
        Assert::assertStringNotContainsString('## Rules', $prompt);
    }

    #[Test]
    public function puts_the_rules_verbatim_before_the_criteria(): void
    {
        $prompt = $this->factory->buildAiQualificationPrompt('Uses Symfony < 6', "Cite the files you inspected.\n");

        Assert::assertStringContainsString("any files.\n\n## Rules\n\nCite the files you inspected.\n\n## Criteria\n\nUses Symfony < 6\n", $prompt);
        Assert::assertStringNotContainsString('## Rules', $this->factory->buildAiQualificationPrompt('Uses Symfony < 6', '  '));
    }

    #[Test]
    public function the_payload_carries_the_criteria_rules(): void
    {
        $payload = $this->factory->build(QualificationCriteria::ai('Uses Symfony < 6', rules: 'Cite paths.'), $this->snapshot());

        Assert::assertStringContainsString("## Rules\n\nCite paths.\n\n## Criteria", (string) $payload['prompt']);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->factory = new QualificationJobPayloadFactory();
    }

    private function snapshot(): ProjectSnapshot
    {
        return new ProjectSnapshot('1', 'group/project', 'Project', 'main');
    }
}
