<?php

declare(strict_types=1);

use Arkitect\Expression\ForClasses\NotResideInTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\*\Domain\*\ReadModel\*'))
    ->should(new NotResideInTheseNamespaces('App\*\Domain\*\ReadModel\*'))
    ->because('ReadModels must be placed in application layer with serialization groups');

