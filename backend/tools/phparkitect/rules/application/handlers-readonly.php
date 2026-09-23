<?php

declare(strict_types=1);

use Arkitect\Expression\ForClasses\HaveNameMatching;
use Arkitect\Expression\ForClasses\IsReadonly;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\*\Application\*'))
    ->andThat(new HaveNameMatching('*Handler'))
    ->should(new IsReadonly())
    ->because('handlers should be readonly to ensure immutability and thread safety');
