<?php

declare(strict_types=1);

namespace App\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<New_>
 */
final class ContextIsolationRule implements Rule
{
    private const CONTEXTS = [
        "File",
        "Identity",
        "Notification",
        "Organization",
        "Project",
        "Playbook",
        "Qualification",
        "Runner",
        "Shift",
    ];

    #[\Override]
    public function getNodeType(): string
    {
        return New_::class;
    }

    #[\Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $className = $scope->getClassReflection();
        if ($className === null) {
            return [];
        }

        $currentClass = $className->getName();

        if (!str_starts_with($currentClass, "App\\")) {
            return [];
        }

        if (str_starts_with($currentClass, "App\\Shared\\") || str_starts_with($currentClass, "App\\Tests\\")) {
            return [];
        }

        $currentContext = $this->getContext($currentClass);
        if ($currentContext === null) {
            return [];
        }

        // Mirrors the carve-outs in tools/phparkitect/rules/context/context-isolation.php:
        // controllers and Application\Command handlers are allowed to coordinate with other
        // contexts (e.g. a controller building another context's Query, or a command handler
        // dispatching another context's Command as part of a cross-context workflow).
        if ($this->isExemptFromIsolation($currentClass)) {
            return [];
        }

        $newClassName = null;

        if ($node->class instanceof Name) {
            $newClassName = $node->class->toString();
        } elseif ($node->class instanceof Node\Expr) {
            $newType = $scope->getType($node->class);
            $classNames = $newType->getObjectClassNames();
            if (count($classNames) === 1) {
                $newClassName = $classNames[0];
            }
        }

        if ($newClassName === null) {
            return [];
        }

        if (!str_starts_with($newClassName, "App\\")) {
            return [];
        }

        if (str_starts_with($newClassName, "App\\Shared\\")) {
            return [];
        }

        if (str_starts_with($newClassName, "App\\{$currentContext}\\")) {
            return [];
        }

        $newContext = $this->getContext($newClassName);
        if ($newContext === null) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                sprintf(
                    "Class %s from context %s cannot instantiate %s from context %s. Contexts must be isolated (only Shared is allowed).",
                    $currentClass,
                    $currentContext,
                    $newClassName,
                    $newContext
                )
            )
            ->identifier("context.isolation")
            ->build()
        ];
    }

    private function getContext(string $className): ?string
    {
        foreach (self::CONTEXTS as $context) {
            if (str_starts_with($className, "App\\{$context}\\")) {
                return $context;
            }
        }

        return null;
    }

    private function isExemptFromIsolation(string $className): bool
    {
        foreach (["\\Infrastructure\\Api\\", "\\Application\\Command\\", "\\Application\\DomainListener\\", "\\Infrastructure\\Command\\"] as $exemptSegment) {
            if (str_contains($className, $exemptSegment)) {
                return true;
            }
        }

        return false;
    }
}