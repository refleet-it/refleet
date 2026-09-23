<?php

declare(strict_types=1);

namespace App\Tests\Unit\Playbook\Playbook\Domain\Playbook\ValueObject;

use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookAppliesToEnum;
use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookKindEnum;
use App\Playbook\Playbook\Domain\Playbook\Exception\InvalidPlaybookException;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookDefinition;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookParameter;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlaybookDefinition::class)]
final class PlaybookDefinitionTest extends TestCase
{
    #[Test]
    public function a_task_may_carry_parameters_and_a_preferred_engine(): void
    {
        $definition = new PlaybookDefinition('t', 'Upgrade', 'desc', PlaybookKindEnum::TASK, PlaybookAppliesToEnum::BOTH, 'Upgrade {{package}}', false, [new PlaybookParameter('package', 'Package', null, true)], CriteriaEngineEnum::CLAUDE, 'claude-opus-5', false);

        Assert::assertSame('Upgrade', $definition->name());
        Assert::assertCount(1, $definition->parameters());
        Assert::assertSame(CriteriaEngineEnum::CLAUDE, $definition->engine());
        Assert::assertTrue($definition->appliesTo()->covers(PlaybookAppliesToEnum::QUALIFICATION));
    }

    #[Test]
    public function a_rule_cannot_declare_parameters(): void
    {
        $this->expectException(InvalidPlaybookException::class);

        new PlaybookDefinition('r', 'Rule', null, PlaybookKindEnum::RULE, PlaybookAppliesToEnum::CHANGE, 'Body', false, [new PlaybookParameter('x', 'X', null, false)], null, null, false);
    }

    #[Test]
    public function a_rule_cannot_pick_an_engine(): void
    {
        $this->expectException(InvalidPlaybookException::class);

        new PlaybookDefinition('r', 'Rule', null, PlaybookKindEnum::RULE, PlaybookAppliesToEnum::CHANGE, 'Body', false, [], CriteriaEngineEnum::KIRO, null, false);
    }

    #[Test]
    public function only_rules_can_be_on_by_default(): void
    {
        $this->expectException(InvalidPlaybookException::class);

        new PlaybookDefinition('t', 'Task', null, PlaybookKindEnum::TASK, PlaybookAppliesToEnum::CHANGE, 'Body', true, [], null, null, false);
    }

    #[Test]
    public function parameter_names_must_be_unique(): void
    {
        $this->expectException(InvalidPlaybookException::class);

        new PlaybookDefinition('t', 'Task', null, PlaybookKindEnum::TASK, PlaybookAppliesToEnum::CHANGE, 'Body', false, [new PlaybookParameter('x', 'X', null, false), new PlaybookParameter('x', 'X again', null, false)], null, null, false);
    }

    #[Test]
    public function the_body_and_name_must_not_be_blank(): void
    {
        $this->expectException(InvalidPlaybookException::class);

        new PlaybookDefinition('t', '  ', null, PlaybookKindEnum::RULE, PlaybookAppliesToEnum::CHANGE, 'Body', false, [], null, null, false);
    }

    #[Test]
    public function a_parameter_name_must_be_an_identifier(): void
    {
        $this->expectException(InvalidPlaybookException::class);

        new PlaybookParameter('1bad name', 'Label', null, false);
    }
}
