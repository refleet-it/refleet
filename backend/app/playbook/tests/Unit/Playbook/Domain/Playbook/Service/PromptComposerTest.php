<?php

declare(strict_types=1);

namespace App\Tests\Unit\Playbook\Playbook\Domain\Playbook\Service;

use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookAppliesToEnum;
use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookKindEnum;
use App\Playbook\Playbook\Domain\Playbook\Exception\MissingPlaybookParameterException;
use App\Playbook\Playbook\Domain\Playbook\Service\PromptComposer;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookDefinition;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookParameter;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\ValueObject\PromptSource;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PromptComposer::class)]
final class PromptComposerTest extends TestCase
{
    private PromptComposer $composer;

    #[Test]
    public function concatenates_rules_under_their_headings_in_the_given_order(): void
    {
        $composed = $this->composer->compose([$this->rule('builtin:git-conventions', 'Git conventions', "Use Conventional Commits.\n"), $this->rule('r2', 'No tests', 'Leave tests alone.')], null, []);

        Assert::assertSame("### Git conventions\n\nUse Conventional Commits.\n\n### No tests\n\nLeave tests alone.", $composed->rules());
        Assert::assertNull($composed->prompt());
        Assert::assertNull($composed->engine());
        Assert::assertSame(
            [['id' => 'builtin:git-conventions', 'name' => 'Git conventions', 'kind' => 'rule', 'builtIn' => true], ['id' => 'r2', 'name' => 'No tests', 'kind' => 'rule', 'builtIn' => false]],
            \array_map(static fn (PromptSource $source): array => $source->toArray(), $composed->sources()),
        );
    }

    #[Test]
    public function renders_the_task_with_parameters_falling_back_to_defaults(): void
    {
        $task = $this->task('t1', 'Upgrade', "Upgrade {{ package }} to {{version}}. Keep {{unknown}} as is.\n", [
            new PlaybookParameter('package', 'Package', null, true),
            new PlaybookParameter('version', 'Version', 'latest', false),
        ], CriteriaEngineEnum::KIRO, 'claude-opus-5');

        $composed = $this->composer->compose([], $task, ['package' => 'acme/lib', 'version' => '  ']);

        Assert::assertNull($composed->rules());
        Assert::assertSame('Upgrade acme/lib to latest. Keep {{unknown}} as is.', $composed->prompt());
        Assert::assertSame(CriteriaEngineEnum::KIRO, $composed->engine());
        Assert::assertSame('claude-opus-5', $composed->model());
        Assert::assertSame([['id' => 't1', 'name' => 'Upgrade', 'kind' => 'task', 'builtIn' => false]], \array_map(static fn (PromptSource $source): array => $source->toArray(), $composed->sources()));
    }

    #[Test]
    public function an_optional_parameter_without_value_or_default_renders_empty(): void
    {
        $task = $this->task('t1', 'Find', 'Find {{package}} {{range}}.', [new PlaybookParameter('package', 'Package', null, true), new PlaybookParameter('range', 'Range', null, false)]);

        Assert::assertSame('Find x .', $this->composer->compose([], $task, ['package' => 'x'])->prompt());
    }

    #[Test]
    public function a_missing_required_parameter_is_refused(): void
    {
        $task = $this->task('t1', 'Upgrade', 'Upgrade {{package}}.', [new PlaybookParameter('package', 'Package', null, true)]);

        $this->expectException(MissingPlaybookParameterException::class);

        $this->composer->compose([], $task, []);
    }

    #[Test]
    public function the_body_is_never_treated_as_a_template_beyond_the_declared_placeholders(): void
    {
        $task = $this->task('t1', 'Echo', 'Literal {% if %} and {{ not_declared }} and {{package}}', [new PlaybookParameter('package', 'Package', null, true)]);

        Assert::assertSame('Literal {% if %} and {{ not_declared }} and {{x}}', $this->composer->compose([], $task, ['package' => '{{x}}'])->prompt());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->composer = new PromptComposer();
    }

    private function rule(string $id, string $name, string $body): PlaybookDefinition
    {
        return new PlaybookDefinition($id, $name, null, PlaybookKindEnum::RULE, PlaybookAppliesToEnum::CHANGE, $body, true, [], null, null, \str_starts_with($id, 'builtin:'));
    }

    /**
     * @param list<PlaybookParameter> $parameters
     */
    private function task(string $id, string $name, string $body, array $parameters, ?CriteriaEngineEnum $engine = null, ?string $model = null): PlaybookDefinition
    {
        return new PlaybookDefinition($id, $name, null, PlaybookKindEnum::TASK, PlaybookAppliesToEnum::CHANGE, $body, false, $parameters, $engine, $model, false);
    }
}
