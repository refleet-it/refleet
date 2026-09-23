<?php

declare(strict_types=1);

use Arkitect\Expression\ForClasses\NotResideInTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;
use Helper\TestClassCorrespondenceRule;

require_once __DIR__ . '/../../helpers/TestClassCorrespondenceRule.php';

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\Tests\Unit\*', 'App\Tests\Integration\*'))
    ->andThat(new NotResideInTheseNamespaces('App\Tests\Helpers\*'))
    ->should(new TestClassCorrespondenceRule())
    ->because('test class path must correspond to the production class path in src');

