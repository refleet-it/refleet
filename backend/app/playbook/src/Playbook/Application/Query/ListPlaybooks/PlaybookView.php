<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Application\Query\ListPlaybooks;

use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookDefinition;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookParameter;

final readonly class PlaybookView
{
    /**
     * @param list<array{name: string, label: string, default: string|null, required: bool}> $parameters
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public string $kind,
        public string $appliesTo,
        public string $body,
        public bool $default,
        public array $parameters,
        public ?string $engine,
        public ?string $model,
        public bool $builtIn,
    ) {
    }

    public static function fromDefinition(PlaybookDefinition $definition): self
    {
        return new self(
            id: $definition->id(),
            name: $definition->name(),
            description: $definition->description(),
            kind: $definition->kind()->value,
            appliesTo: $definition->appliesTo()->value,
            body: $definition->body(),
            default: $definition->isDefault(),
            parameters: \array_map(static fn (PlaybookParameter $parameter): array => $parameter->toArray(), $definition->parameters()),
            engine: $definition->engine()?->value,
            model: $definition->model(),
            builtIn: $definition->builtIn(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'kind' => $this->kind,
            'appliesTo' => $this->appliesTo,
            'body' => $this->body,
            'default' => $this->default,
            'parameters' => $this->parameters,
            'engine' => $this->engine,
            'model' => $this->model,
            'builtIn' => $this->builtIn,
        ];
    }
}
