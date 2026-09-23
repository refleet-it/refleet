<?php

declare(strict_types=1);

use Arkitect\Expression\ForClasses\IsReadonly;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\*\Domain\*\ValueObject\*'))
    ->should(new IsReadonly())
    ->because('value objects must be readonly to enforce immutability');
