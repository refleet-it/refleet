<?php

declare(strict_types=1);

use Arkitect\Expression\ForClasses\IsReadonly;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\*\Domain\*\Policy\*'))
    ->should(new IsReadonly())
    ->because('domain policies should be readonly to ensure statelessness');
