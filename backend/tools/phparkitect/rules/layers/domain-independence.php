<?php

declare(strict_types=1);

use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\*\Domain\*'))
    ->should(new NotDependsOnTheseNamespaces([
        'App\*\Application\*',
        'App\*\Infrastructure\*',
        'App\*\Presentation\*',
    ]))
    ->because('the domain layer must be independent from the other application layers');

