<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use Webmozart\Assert\Assert;

/**
 * Which playbook a piece of a prompt was composed from. Recorded on a Shift/Qualification
 * purely for display: the prompt text itself is the snapshot the agent runs, so a source
 * is never resolved again after composition.
 */
final readonly class PromptSource
{
    public function __construct(
        private string $id,
        private string $name,
        private string $kind,
        private bool $builtIn,
    ) {
        Assert::stringNotEmpty($id, 'Prompt source id must not be empty');
        Assert::stringNotEmpty($name, 'Prompt source name must not be empty');
        Assert::inArray($kind, ['task', 'rule'], 'Prompt source kind must be "task" or "rule"');
    }

    /**
     * @param array{id: string, name: string, kind: string, builtIn?: bool} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['id'], $data['name'], $data['kind'], $data['builtIn'] ?? false);
    }

    /**
     * @return array{id: string, name: string, kind: string, builtIn: bool}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'kind' => $this->kind, 'builtIn' => $this->builtIn];
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function builtIn(): bool
    {
        return $this->builtIn;
    }
}
