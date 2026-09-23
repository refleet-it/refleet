<?php

declare(strict_types=1);

use Arkitect\Expression\ForClasses\NotHaveNameMatching;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\*\Application\*'))
    ->should(new NotHaveNameMatching('*Service'))
    ->because('Generic *Service in Application is forbidden (use CQRS artifacts)');

