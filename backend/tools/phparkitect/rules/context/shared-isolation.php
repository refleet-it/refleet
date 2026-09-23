<?php

declare(strict_types=1);

use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

/**
 * Returns rule enforcing that Shared context cannot depend on other bounded contexts.
 *
 * @param array<string> $contexts List of all contexts
 * @param callable $ns Helper function to build namespace patterns
 */
return static function (array $contexts, callable $ns) {
    if (!in_array('Shared', $contexts, true)) {
        return null;
    }

    $blockedForShared = array_map(
        static fn (string $other) => $ns($other),
        array_values(array_filter($contexts, static fn ($c) => $c !== 'Shared'))
    );

    if (empty($blockedForShared)) {
        return null;
    }

    return Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces('App\Shared\*'))
        ->should(new NotDependsOnTheseNamespaces($blockedForShared))
        ->because('Shared context cannot depend on other bounded contexts');
};

