<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Infrastructure\Service;

use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookAppliesToEnum;
use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookKindEnum;
use App\Playbook\Playbook\Domain\Playbook\Service\BuiltInPlaybookCatalogInterface;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookDefinition;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookParameter;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads the built-in playbooks from app/playbook/resources/playbooks/<id>.md — YAML
 * front matter for the metadata, the rest of the file as the body. Kept as files, not
 * PHP, so the prompts are reviewed as prose and the format matches what a future
 * git-synced organization catalog would use.
 */
final class MarkdownBuiltInPlaybookCatalog implements BuiltInPlaybookCatalogInterface
{
    private const string FRONT_MATTER_PATTERN = '/\A---\R(.*?)\R---\R?(.*)\z/s';

    /** @var list<PlaybookDefinition>|null */
    private ?array $definitions = null;

    public function __construct(
        private readonly string $directory,
    ) {
    }

    #[\Override]
    public function all(): array
    {
        return $this->definitions ??= $this->load();
    }

    #[\Override]
    public function find(string $id): ?PlaybookDefinition
    {
        foreach ($this->all() as $definition) {
            if ($definition->id() === $id) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @return list<PlaybookDefinition>
     */
    private function load(): array
    {
        $files = \glob($this->directory.'/*.md');
        if (false === $files) {
            return [];
        }

        \sort($files);

        return \array_values(\array_map($this->parseFile(...), $files));
    }

    private function parseFile(string $path): PlaybookDefinition
    {
        $contents = \file_get_contents($path);
        if (false === $contents || 1 !== \preg_match(self::FRONT_MATTER_PATTERN, $contents, $match)) {
            throw new \RuntimeException(\sprintf('Built-in playbook %s must start with a YAML front matter block.', $path));
        }

        /** @var array<string, mixed> $meta */
        $meta = Yaml::parse($match[1]) ?? [];
        /** @var list<array{name: string, label?: string|null, default?: string|null, required?: bool}> $parameters */
        $parameters = \is_array($meta['parameters'] ?? null) ? \array_values($meta['parameters']) : [];
        $engine = $this->text($meta, 'engine');

        return new PlaybookDefinition(
            id: PlaybookDefinition::BUILT_IN_ID_PREFIX.\basename($path, '.md'),
            name: $this->text($meta, 'name') ?? \basename($path, '.md'),
            description: $this->text($meta, 'description'),
            kind: PlaybookKindEnum::from($this->text($meta, 'kind') ?? 'rule'),
            appliesTo: PlaybookAppliesToEnum::from($this->text($meta, 'appliesTo') ?? 'both'),
            body: \trim($match[2]),
            default: true === ($meta['default'] ?? false),
            parameters: \array_map(PlaybookParameter::fromArray(...), $parameters),
            engine: null === $engine ? null : CriteriaEngineEnum::from($engine),
            model: $this->text($meta, 'model'),
            builtIn: true,
        );
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function text(array $meta, string $key): ?string
    {
        $value = $meta[$key] ?? null;

        return \is_string($value) && '' !== \trim($value) ? $value : null;
    }
}
