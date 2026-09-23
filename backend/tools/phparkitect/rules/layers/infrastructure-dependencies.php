<?php

declare(strict_types=1);

use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

/**
 * The Application restriction below stays off, and not because of serializers as the previous note
 * claimed. Enabling it reports 99 violations across 67 files in every context: 59 are HTTP
 * controllers under Infrastructure\Api, whose whole job is to dispatch a command or a query, and
 * the rest are queue handlers and port implementations doing the same. Both rule sets here name an
 * App\*\Presentation\* layer that this codebase never grew — the controllers went to
 * Infrastructure\Api instead, which is what makes the dependency unavoidable. Turning the rule on
 * means either moving those controllers or narrowing the rule to the adapter folders.
 */
return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\*\Infrastructure\*'))
    ->should(new NotDependsOnTheseNamespaces([
        // 'App\*\Application\*',
        'App\*\Presentation\*',
    ]))
    ->because('the infrastructure layer may not reach into presentation');

