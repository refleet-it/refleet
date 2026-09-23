<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Domain\Playbook\ValueObject;

use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookAppliesToEnum;
use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookKindEnum;
use App\Playbook\Playbook\Domain\Playbook\Exception\InvalidPlaybookException;
use App\Shared\Domain\Enum\CriteriaEngineEnum;

/**
 * What a playbook is, independent of where it lives: the organization's own ones are
 * persisted (see the Playbook aggregate), the built-in ones ship with Refleet as files.
 * Both surface as this, so listing and composition never care which is which.
 */
final readonly class PlaybookDefinition
{
    public const int NAME_MAX_LENGTH = 120;

    public const int BODY_MAX_LENGTH = 20000;

    public const string BUILT_IN_ID_PREFIX = 'builtin:';

    /**
     * @param list<PlaybookParameter> $parameters
     */
    public function __construct(
        private string $id,
        private string $name,
        private ?string $description,
        private PlaybookKindEnum $kind,
        private PlaybookAppliesToEnum $appliesTo,
        private string $body,
        private bool $default,
        private array $parameters,
        private ?CriteriaEngineEnum $engine,
        private ?string $model,
        private bool $builtIn,
    ) {
        $this->guardText($name, $body);
        $this->guardKind($kind, $default, $parameters, $engine, $model);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function kind(): PlaybookKindEnum
    {
        return $this->kind;
    }

    public function appliesTo(): PlaybookAppliesToEnum
    {
        return $this->appliesTo;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function isDefault(): bool
    {
        return $this->default;
    }

    /**
     * @return list<PlaybookParameter>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    public function engine(): ?CriteriaEngineEnum
    {
        return $this->engine;
    }

    public function model(): ?string
    {
        return $this->model;
    }

    public function builtIn(): bool
    {
        return $this->builtIn;
    }

    private function guardText(string $name, string $body): void
    {
        if ('' === \trim($name) || \mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new InvalidPlaybookException(\sprintf('Playbook name must be 1-%d characters.', self::NAME_MAX_LENGTH));
        }

        if ('' === \trim($body)) {
            throw new InvalidPlaybookException('Playbook body must not be empty.');
        }

        if (\mb_strlen($body) > self::BODY_MAX_LENGTH) {
            throw new InvalidPlaybookException(\sprintf('Playbook body must be at most %d characters.', self::BODY_MAX_LENGTH));
        }
    }

    /**
     * @param list<PlaybookParameter> $parameters
     */
    private function guardKind(PlaybookKindEnum $kind, bool $default, array $parameters, ?CriteriaEngineEnum $engine, ?string $model): void
    {
        if (PlaybookKindEnum::RULE === $kind && ([] !== $parameters || null !== $engine || null !== $model)) {
            throw new InvalidPlaybookException('A rule playbook cannot declare parameters, an engine or a model — only tasks do.');
        }

        if (PlaybookKindEnum::TASK === $kind && $default) {
            throw new InvalidPlaybookException('Only rule playbooks can be on by default.');
        }

        $names = \array_map(static fn (PlaybookParameter $parameter): string => $parameter->name(), $parameters);
        if (\count($names) !== \count(\array_unique($names))) {
            throw new InvalidPlaybookException('Parameter names must be unique within a playbook.');
        }
    }
}
