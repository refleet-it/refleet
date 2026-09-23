<?php

declare(strict_types=1);

use Arkitect\Expression\ForClasses\NotHaveNameMatching;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\*'))
    ->andThat(new NotHaveNameMatching('Kernel'))
    ->should(new ResideInOneOfTheseNamespaces(
        'App\*\Domain\*',
        'App\*\Application\*',
        'App\*\Infrastructure\*',
        'App\*\Presentation\*',
        'App\Story\*',
    ))
    ->because('context may directly contain only a namespace related to DDD or testing (Story)');

