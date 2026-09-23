<?php

declare(strict_types=1);

use Arkitect\Expression\ForClasses\NotResideInTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\*\Application\Service\*'))
    ->should(new NotResideInTheseNamespaces('App\*\Application\Service\*'))
    ->because('Application/Service namespace is forbidden; use Commands/Queries/Handlers');

