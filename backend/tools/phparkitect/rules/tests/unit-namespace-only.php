<?php

declare(strict_types=1);

use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\Tests\*'))
    ->should(new ResideInOneOfTheseNamespaces('App\Tests\Unit\*', 'App\Tests\Helpers\*', 'App\Tests\Functional\*', 'App\Tests\Integration\*'))
    ->because('all tests must be in tests/Unit, tests/Helpers, tests/Functional, or tests/Integration namespace');
