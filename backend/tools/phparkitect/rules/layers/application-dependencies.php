<?php

declare(strict_types=1);

use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\*\Application\*'))
    ->should(new NotDependsOnTheseNamespaces([
        'App\*\Presentation\*',
    ]))
    ->because('the application layer can only use the domain and infrastructure');

