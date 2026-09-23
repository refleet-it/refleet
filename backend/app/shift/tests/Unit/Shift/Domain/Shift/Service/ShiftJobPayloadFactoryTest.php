<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Domain\Shift\Service;

use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use App\Shift\Shift\Domain\Shift\Service\ShiftJobPayloadFactory;
use App\Shift\Shift\Domain\Shift\ValueObject\ChangeCriteria;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShiftJobPayloadFactory::class)]
final class ShiftJobPayloadFactoryTest extends TestCase
{
    private ShiftJobPayloadFactory $factory;

    #[Test]
    public function builds_a_payload_that_wraps_the_change_in_the_agent_instructions(): void
    {
        $payload = $this->factory->build(
            ChangeCriteria::ai('Bump acme/legacy-lib to ^3.0', 'claude-opus-5', CriteriaEngineEnum::KIRO),
            new ProjectSnapshot('1', 'group/project', 'Project', 'main'),
        );

        Assert::assertSame('ai', $payload['mode']);
        Assert::assertSame('kiro', $payload['engine']);
        Assert::assertSame('claude-opus-5', $payload['model']);
        Assert::assertStringContainsString("## Change\n\nBump acme/legacy-lib to ^3.0\n", (string) $payload['prompt']);
        Assert::assertSame(
            ['externalId' => '1', 'path' => 'group/project', 'name' => 'Project', 'defaultBranch' => 'main'],
            $payload['project'],
        );
    }

    #[Test]
    public function defaults_the_engine_to_claude(): void
    {
        $payload = $this->factory->build(ChangeCriteria::ai('Bump acme/legacy-lib to ^3.0'), new ProjectSnapshot('1', 'group/project', 'Project', null));

        Assert::assertSame('claude', $payload['engine']);
    }

    #[Test]
    public function the_prompt_demands_a_json_summary_and_keeps_git_out_of_the_agents_hands(): void
    {
        $prompt = $this->factory->buildAiChangePrompt("  Bump acme/legacy-lib to ^3.0\n");

        Assert::assertStringContainsString("## Change\n\nBump acme/legacy-lib to ^3.0\n\n## Answer format", $prompt);
        Assert::assertStringContainsString('{"summary": "<2-3 sentences>", "commit": {"subject": "<one line, at most 72 characters>", "body": "<optional, may be empty>"}, "mergeRequest": {"title": "<one line>", "description": "<markdown>"}}', $prompt);
        Assert::assertStringContainsString('Do not create branches, commit, push, or open a merge request', $prompt);
        Assert::assertStringContainsString('the wording the tooling uses when it commits your', $prompt);
        Assert::assertStringContainsString('do not ask questions', $prompt);
        Assert::assertStringNotContainsString('## Rules', $prompt);
    }

    #[Test]
    public function puts_the_rules_verbatim_between_the_framing_and_the_change(): void
    {
        $prompt = $this->factory->buildAiChangePrompt('Bump acme/legacy-lib to ^3.0', "### Git conventions\n\nConventional Commits, English.\n");

        Assert::assertStringContainsString("say so.\n\n## Rules\n\n### Git conventions\n\nConventional Commits, English.\n\n## Change\n\nBump acme/legacy-lib to ^3.0\n", $prompt);
    }

    #[Test]
    public function blank_rules_leave_no_empty_section_behind(): void
    {
        Assert::assertStringNotContainsString('## Rules', $this->factory->buildAiChangePrompt('Bump it', "  \n"));
    }

    #[Test]
    public function the_payload_carries_the_criteria_rules(): void
    {
        $payload = $this->factory->build(
            ChangeCriteria::ai('Bump acme/legacy-lib to ^3.0', rules: 'Never touch tests.'),
            new ProjectSnapshot('1', 'group/project', 'Project', 'main'),
        );

        Assert::assertStringContainsString("## Rules\n\nNever touch tests.\n\n## Change", (string) $payload['prompt']);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->factory = new ShiftJobPayloadFactory();
    }
}
