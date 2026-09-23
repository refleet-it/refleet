<?php

declare(strict_types=1);

namespace App\Tests\Integration\Playbook\Playbook\Infrastructure\Persistence;

use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookAppliesToEnum;
use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookKindEnum;
use App\Playbook\Playbook\Domain\Playbook\Model\Playbook;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\AccountId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\OrganizationId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookDefinition;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookParameter;
use App\Playbook\Playbook\Infrastructure\Persistence\DoctrinePlaybookRepository;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[CoversClass(DoctrinePlaybookRepository::class)]
final class DoctrinePlaybookRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private DoctrinePlaybookRepository $repository;

    private EntityManagerInterface $entityManager;

    #[Test]
    public function saves_a_playbook_with_its_parameters_and_finds_it_within_its_organization_only(): void
    {
        $id = PlaybookId::generate();
        $organizationId = OrganizationId::generate();
        $this->repository->save(Playbook::create($id, $organizationId, AccountId::generate(), new PlaybookDefinition(
            'x',
            'Upgrade',
            'desc',
            PlaybookKindEnum::TASK,
            PlaybookAppliesToEnum::CHANGE,
            'Upgrade {{package}}',
            false,
            [new PlaybookParameter('package', 'Package', 'acme/lib', true)],
            CriteriaEngineEnum::CLAUDE,
            'claude-opus-5',
            false,
        )));
        $this->entityManager->clear();

        $found = $this->repository->findByIdForOrganization($id, $organizationId);

        Assert::assertInstanceOf(Playbook::class, $found);
        $definition = $found->definition();
        Assert::assertSame('Upgrade', $definition->name());
        Assert::assertSame(['name' => 'package', 'label' => 'Package', 'default' => 'acme/lib', 'required' => true], $definition->parameters()[0]->toArray());
        Assert::assertSame(CriteriaEngineEnum::CLAUDE, $definition->engine());
        Assert::assertNull($this->repository->findByIdForOrganization($id, OrganizationId::generate()));
    }

    #[Test]
    public function lists_an_organizations_playbooks_by_name_and_removes_them(): void
    {
        $organizationId = OrganizationId::generate();
        $zebra = Playbook::create(PlaybookId::generate(), $organizationId, AccountId::generate(), $this->rule('Zebra'));
        $alpha = Playbook::create(PlaybookId::generate(), $organizationId, AccountId::generate(), $this->rule('Alpha'));
        $other = Playbook::create(PlaybookId::generate(), OrganizationId::generate(), AccountId::generate(), $this->rule('Other org'));
        $this->repository->save($zebra);
        $this->repository->save($alpha);
        $this->repository->save($other);

        $this->entityManager->clear();

        Assert::assertSame(['Alpha', 'Zebra'], \array_map(static fn (Playbook $playbook): string => $playbook->definition()->name(), $this->repository->findByOrganizationId($organizationId)));

        $found = $this->repository->findByIdForOrganization($alpha->id(), $organizationId);
        Assert::assertNotNull($found);
        $this->repository->remove($found);
        $this->entityManager->clear();

        Assert::assertSame(['Zebra'], \array_map(static fn (Playbook $playbook): string => $playbook->definition()->name(), $this->repository->findByOrganizationId($organizationId)));
    }

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->repository = $container->get(DoctrinePlaybookRepository::class);

        $managerRegistry = $container->get(ManagerRegistry::class);
        $entityManager = $managerRegistry->getManagerForClass(Playbook::class);
        Assert::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    private function rule(string $name): PlaybookDefinition
    {
        return new PlaybookDefinition('x', $name, null, PlaybookKindEnum::RULE, PlaybookAppliesToEnum::BOTH, 'Body of '.$name, false, [], null, null, false);
    }
}
