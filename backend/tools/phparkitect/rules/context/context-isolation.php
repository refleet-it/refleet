<?php

declare(strict_types=1);

use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\NotResideInTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

/**
 * Returns array of rules enforcing context isolation.
 * Each context cannot depend on other contexts (except Shared) — no layer is exempt.
 *
 * Contexts reach each other in exactly two ways, neither of which is an import:
 * - a port declared in App\Shared\Domain\Service, implemented by the owning context and
 *   wired through an alias in config/services.yaml (synchronous reads);
 * - a message published on a queue, where producer and consumer each keep their own copy
 *   of the message class and share only the wire contract (writes and notifications).
 *
 * @param array<string> $contexts List of all contexts
 * @param callable $ns Helper function to build namespace patterns
 */
return static function (array $contexts, callable $ns): array {
    $contextsNoShared = array_values(array_filter($contexts, static fn ($c) => $c !== 'Shared'));
    $rules = [];

    foreach ($contextsNoShared as $ctx) {
        $blocked = array_map(
            static fn (string $other) => $ns($other),
            array_values(array_filter($contexts, static fn ($c) => $c !== $ctx && $c !== 'Shared'))
        );

        if (!empty($blocked)) {
            $rules[] = Rule::allClasses()
                ->that(new ResideInOneOfTheseNamespaces($ns($ctx)))
                ->andThat(new NotResideInTheseNamespaces('App\Shared\*'))
                ->should(new NotDependsOnTheseNamespaces($blocked))
                ->because(sprintf(
                    '%s context cannot use files from other contexts (except Shared) — reach them through a port in App\Shared\Domain\Service or a queued message instead',
                    $ctx
                ));
        }
    }

    return $rules;
};

