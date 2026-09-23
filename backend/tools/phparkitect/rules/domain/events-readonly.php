<?php

declare(strict_types=1);

use Arkitect\Expression\ForClasses\IsReadonly;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\*\Domain\*\Event\*'))
    ->should(new IsReadonly())
    ->because('domain events must be readonly to ensure they cannot be modified after creation');
