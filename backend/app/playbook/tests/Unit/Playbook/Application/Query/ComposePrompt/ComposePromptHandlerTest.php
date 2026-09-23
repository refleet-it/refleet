<?php

declare(strict_types=1);

namespace App\Tests\Unit\Playbook\Playbook\Application\Query\ComposePrompt;

use App\Playbook\Playbook\Application\Query\ComposePrompt\ComposePromptHandler;
use App\Playbook\Playbook\Application\Query\ComposePrompt\ComposePromptQuery;
use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookAppliesToEnum;
use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookKindEnum;
use App\Playbook\Playbook\Domain\Playbook\Exception\InvalidPlaybookException;
use App\Playbook\Playbook\Domain\Playbook\Exception\PlaybookNotFoundException;
use App\Playbook\Playbook\Domain\Playbook\Model\Playbook;
use App\Playbook\Playbook\Domain\Playbook\Repository\PlaybookRepositoryInterface;
use App\Playbook\Playbook\Domain\Playbook\Service\BuiltInPlaybookCatalogInterface;
use App\Playbook\Playbook\Domain\Playbook\Service\PlaybookDirectory;
use App\Playbook\Playbook\Domain\Playbook\Service\PromptComposer;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\AccountId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\OrganizationId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookDefinition;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookParameter;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComposePromptHandler::class)]
#[CoversClass(PlaybookDirectory::class)]
final class ComposePromptHandlerTest extends TestCase
{
    private const string ORGANIZATION = '018f0000-0000-7000-8000-000000000001';

    private ComposePromptHandler $handler;

    private PlaybookId $ownTaskId;

    #[Test]
    public function composes_built_in_rules_with_an_organization_task(): void
    {
        $composed = ($this->handler)(new ComposePromptQuery(self::ORGANIZATION, 'change', ['builtin:git'], $this->ownTaskId->asString(), ['package' => 'acme/lib']));

        Assert::assertSame("### Git\n\nConventional Commits.", $composed->rules());
        Assert::assertSame('Upgrade acme/lib.', $composed->prompt());
        Assert::assertSame(['builtin:git', $this->ownTaskId->asString()], \array_map(static fn ($source): string => $source->id(), $composed->sources()));
    }

    #[Test]
    public function an_unknown_playbook_is_not_found(): void
    {
        $this->expectException(PlaybookNotFoundException::class);

        ($this->handler)(new ComposePromptQuery(self::ORGANIZATION, 'change', ['builtin:nope'], null, []));
    }

    #[Test]
    public function a_task_cannot_be_used_as_a_rule(): void
    {
        $this->expectException(InvalidPlaybookException::class);

        ($this->handler)(new ComposePromptQuery(self::ORGANIZATION, 'change', [$this->ownTaskId->asString()], null, []));
    }

    #[Test]
    public function a_change_only_rule_does_not_apply_to_a_qualification(): void
    {
        $this->expectException(InvalidPlaybookException::class);

        ($this->handler)(new ComposePromptQuery(self::ORGANIZATION, 'qualification', ['builtin:git'], null, []));
    }

    #[Test]
    public function a_malformed_id_is_simply_not_found(): void
    {
        $this->expectException(PlaybookNotFoundException::class);

        ($this->handler)(new ComposePromptQuery(self::ORGANIZATION, 'change', [], 'not-a-uuid', []));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->ownTaskId = PlaybookId::generate();
        $task = Playbook::create($this->ownTaskId, OrganizationId::fromString(self::ORGANIZATION), AccountId::generate(), new PlaybookDefinition(
            'x',
            'Upgrade',
            null,
            PlaybookKindEnum::TASK,
            PlaybookAppliesToEnum::CHANGE,
            'Upgrade {{package}}.',
            false,
            [new PlaybookParameter('package', 'Package', null, true)],
            null,
            null,
            false,
        ));
        $git = new PlaybookDefinition('builtin:git', 'Git', null, PlaybookKindEnum::RULE, PlaybookAppliesToEnum::CHANGE, 'Conventional Commits.', true, [], null, null, true);

        $repository = $this->createStub(PlaybookRepositoryInterface::class);
        $repository->method('findByIdForOrganization')->willReturnCallback(
            fn (PlaybookId $id, OrganizationId $organizationId): ?Playbook => $id->equals($this->ownTaskId) && self::ORGANIZATION === $organizationId->asString() ? $task : null,
        );
        $catalog = $this->createStub(BuiltInPlaybookCatalogInterface::class);
        $catalog->method('all')->willReturn([$git]);
        $catalog->method('find')->willReturnCallback(static fn (string $id): ?PlaybookDefinition => 'builtin:git' === $id ? $git : null);

        $this->handler = new ComposePromptHandler(new PlaybookDirectory($repository, $catalog), new PromptComposer());
    }
}
