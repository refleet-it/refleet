<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Domain\Playbook\ValueObject;

use App\Playbook\Playbook\Domain\Playbook\Exception\InvalidPlaybookException;

/**
 * A `{{name}}` placeholder a task playbook takes at composition time.
 */
final readonly class PlaybookParameter
{
    public const string NAME_PATTERN = '/^[a-zA-Z]\w{0,39}$/';

    public function __construct(
        private string $name,
        private string $label,
        private ?string $default,
        private bool $required,
    ) {
        if (1 !== \preg_match(self::NAME_PATTERN, $name)) {
            throw new InvalidPlaybookException(\sprintf('Parameter name "%s" must be a letter followed by letters, digits or underscores (at most 40 characters).', $name));
        }

        if ('' === \trim($label)) {
            throw new InvalidPlaybookException(\sprintf('Parameter "%s" needs a label.', $name));
        }
    }

    /**
     * @param array{name: string, label?: string|null, default?: string|null, required?: bool} $data
     */
    public static function fromArray(array $data): self
    {
        $label = $data['label'] ?? null;

        return new self(
            $data['name'],
            null === $label || '' === \trim($label) ? $data['name'] : $label,
            $data['default'] ?? null,
            $data['required'] ?? false,
        );
    }

    /**
     * @return array{name: string, label: string, default: string|null, required: bool}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'label' => $this->label, 'default' => $this->default, 'required' => $this->required];
    }

    public function name(): string
    {
        return $this->name;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function default(): ?string
    {
        return $this->default;
    }

    public function required(): bool
    {
        return $this->required;
    }
}
