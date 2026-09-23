<?php

declare(strict_types=1);

use Arkitect\Expression\ForClasses\HaveNameMatching;
use Arkitect\Expression\ForClasses\IsReadonly;
use Arkitect\Expression\ForClasses\NotHaveNameMatching;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\*\Application\Command\*'))
    ->andThat(new HaveNameMatching('*Command'))
    ->andThat(new NotHaveNameMatching('*CommandHandler'))
    ->should(new IsReadonly())
    ->because('commands should be readonly to ensure immutability');
