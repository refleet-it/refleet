<?php

declare(strict_types=1);

namespace App\Tests\Unit\Playbook\Playbook\Infrastructure\Service;

use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookAppliesToEnum;
use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookKindEnum;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookDefinition;
use App\Playbook\Playbook\Infrastructure\Service\MarkdownBuiltInPlaybookCatalog;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownBuiltInPlaybookCatalog::class)]
final class MarkdownBuiltInPlaybookCatalogTest extends TestCase
{
    #[Test]
    public function parses_front_matter_and_body_from_a_directory_of_markdown_files(): void
    {
        $directory = \sys_get_temp_dir().'/playbooks-'.\uniqid();
        \mkdir($directory);
        \file_put_contents($directory.'/upgrade.md', "---\nname: Upgrade\nkind: task\nappliesTo: change\ndescription: Bump it\nengine: kiro\nmodel: m1\nparameters:\n  - name: package\n    label: Package\n    required: true\n---\nUpgrade {{package}}.\n");
        \file_put_contents($directory.'/bare.md', "---\ndefault: true\n---\n\nJust a rule body.\n");
        \file_put_contents($directory.'/notes.txt', 'ignored');

        $catalog = new MarkdownBuiltInPlaybookCatalog($directory);
        $all = $catalog->all();

        Assert::assertSame(['builtin:bare', 'builtin:upgrade'], \array_map(static fn (PlaybookDefinition $definition): string => $definition->id(), $all));

        $bare = $catalog->find('builtin:bare');
        Assert::assertNotNull($bare);
        Assert::assertSame('bare', $bare->name());
        Assert::assertSame(PlaybookKindEnum::RULE, $bare->kind());
        Assert::assertSame(PlaybookAppliesToEnum::BOTH, $bare->appliesTo());
        Assert::assertTrue($bare->isDefault());
        Assert::assertTrue($bare->builtIn());
        Assert::assertSame('Just a rule body.', $bare->body());

        $upgrade = $catalog->find('builtin:upgrade');
        Assert::assertNotNull($upgrade);
        Assert::assertSame(PlaybookKindEnum::TASK, $upgrade->kind());
        Assert::assertSame('Bump it', $upgrade->description());
        Assert::assertSame('kiro', $upgrade->engine()?->value);
        Assert::assertSame('m1', $upgrade->model());
        Assert::assertSame('package', $upgrade->parameters()[0]->name());
        Assert::assertTrue($upgrade->parameters()[0]->required());
        Assert::assertNull($catalog->find('builtin:missing'));
    }

    #[Test]
    public function the_shipped_catalog_loads_and_git_conventions_is_a_default_change_rule(): void
    {
        $catalog = new MarkdownBuiltInPlaybookCatalog(\dirname(__DIR__, 5).'/resources/playbooks');

        $gitConventions = $catalog->find('builtin:git-conventions');

        Assert::assertNotNull($gitConventions);
        Assert::assertSame(PlaybookKindEnum::RULE, $gitConventions->kind());
        Assert::assertSame(PlaybookAppliesToEnum::CHANGE, $gitConventions->appliesTo());
        Assert::assertTrue($gitConventions->isDefault());
        Assert::assertGreaterThanOrEqual(5, \count($catalog->all()));
    }

    #[Test]
    public function a_file_without_front_matter_is_a_configuration_error(): void
    {
        $directory = \sys_get_temp_dir().'/playbooks-'.\uniqid();
        \mkdir($directory);
        \file_put_contents($directory.'/broken.md', "No front matter here.\n");

        $this->expectException(\RuntimeException::class);

        (new MarkdownBuiltInPlaybookCatalog($directory))->all();
    }
}
