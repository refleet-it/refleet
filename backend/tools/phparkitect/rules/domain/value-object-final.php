<?php

declare(strict_types=1);

use Arkitect\Expression\ForClasses\IsFinal;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\*\Domain\*\ValueObject\*'))
    ->should(new IsFinal())
    ->because('value objects are immutable and should not be inherited');

