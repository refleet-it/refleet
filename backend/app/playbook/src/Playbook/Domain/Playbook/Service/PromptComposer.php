<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Domain\Playbook\Service;

use App\Playbook\Playbook\Domain\Playbook\Exception\MissingPlaybookParameterException;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\ComposedPrompt;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookDefinition;
use App\Shared\Domain\ValueObject\PromptSource;

/**
 * Turns a selection of playbooks into text. Rules are concatenated under their own
 * headings, in the order given; the task body has its `{{name}}` placeholders filled from
 * the parameters (or their defaults). A plain string replacement on purpose — the body is
 * user-authored prompt text and must never reach a template engine.
 */
final readonly class PromptComposer
{
    private const string PLACEHOLDER_PATTERN = '/\{\{\s*([a-zA-Z]\w*)\s*\}\}/';

    /**
     * @param list<PlaybookDefinition> $rules
     * @param array<string, string>    $parameters
     */
    public function compose(array $rules, ?PlaybookDefinition $task, array $parameters): ComposedPrompt
    {
        $sources = \array_map($this->sourceOf(...), $rules);
        if (null !== $task) {
            $sources[] = $this->sourceOf($task);
        }

        return new ComposedPrompt(
            rules: [] === $rules ? null : \implode("\n\n", \array_map(static fn (PlaybookDefinition $rule): string => '### '.$rule->name()."\n\n".\trim($rule->body()), $rules)),
            prompt: null === $task ? null : $this->render($task, $parameters),
            engine: $task?->engine(),
            model: $task?->model(),
            sources: $sources,
        );
    }

    /**
     * @param array<string, string> $parameters
     */
    private function render(PlaybookDefinition $task, array $parameters): string
    {
        $values = [];
        foreach ($task->parameters() as $parameter) {
            $given = $parameters[$parameter->name()] ?? null;
            $value = null === $given || '' === \trim($given) ? $parameter->default() : $given;
            if (null === $value || '' === \trim($value)) {
                if ($parameter->required()) {
                    throw new MissingPlaybookParameterException($task->name(), $parameter->name());
                }

                $value = '';
            }

            $values[$parameter->name()] = $value;
        }

        $rendered = \preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            static fn (array $match): string => $values[$match[1]] ?? $match[0],
            \trim($task->body()),
        );

        return $rendered ?? \trim($task->body());
    }

    private function sourceOf(PlaybookDefinition $playbook): PromptSource
    {
        return new PromptSource($playbook->id(), $playbook->name(), $playbook->kind()->value, $playbook->builtIn());
    }
}
