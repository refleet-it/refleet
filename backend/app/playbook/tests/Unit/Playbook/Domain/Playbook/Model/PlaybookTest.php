<?php

declare(strict_types=1);

namespace App\Tests\Unit\Playbook\Playbook\Domain\Playbook\Model;

use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookAppliesToEnum;
use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookKindEnum;
use App\Playbook\Playbook\Domain\Playbook\Model\Playbook;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\AccountId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\OrganizationId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookDefinition;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookParameter;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Playbook::class)]
final class PlaybookTest extends TestCase
{
    #[Test]
    public function exposes_what_it_was_created_with_as_a_definition_keyed_by_its_own_id(): void
    {
        $id = PlaybookId::generate();
        $playbook = Playbook::create($id, OrganizationId::generate(), AccountId::generate(), new PlaybookDefinition(
            'ignored',
            'Upgrade',
            'desc',
            PlaybookKindEnum::TASK,
            PlaybookAppliesToEnum::CHANGE,
            'Upgrade {{package}}',
            false,
            [new PlaybookParameter('package', 'Package', 'x', true)],
            null,
            null,
            true,
        ));

        $definition = $playbook->definition();

        Assert::assertSame($id->asString(), $definition->id());
        Assert::assertFalse($definition->builtIn());
        Assert::assertSame('Upgrade', $definition->name());
        Assert::assertSame(['name' => 'package', 'label' => 'Package', 'default' => 'x', 'required' => true], $definition->parameters()[0]->toArray());
    }

    #[Test]
    public function update_replaces_the_definition(): void
    {
        $playbook = Playbook::create(PlaybookId::generate(), OrganizationId::generate(), AccountId::generate(), new PlaybookDefinition(
            'x',
            'Rule',
            null,
            PlaybookKindEnum::RULE,
            PlaybookAppliesToEnum::CHANGE,
            'Old',
            false,
            [],
            null,
            null,
            false,
        ));

        $playbook->update(new PlaybookDefinition('x', 'Rule v2', 'now with description', PlaybookKindEnum::RULE, PlaybookAppliesToEnum::BOTH, 'New', true, [], null, null, false));

        $definition = $playbook->definition();
        Assert::assertSame('Rule v2', $definition->name());
        Assert::assertSame('now with description', $definition->description());
        Assert::assertSame('New', $definition->body());
        Assert::assertTrue($definition->isDefault());
        Assert::assertSame(PlaybookAppliesToEnum::BOTH, $definition->appliesTo());
    }
}
