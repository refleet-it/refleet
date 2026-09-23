<?php

declare(strict_types=1);

use App\Shared\Application\Command\Sync\CommandInterface;
use Arkitect\Expression\ForClasses\HaveNameMatching;
use Arkitect\Expression\ForClasses\Implement;
use Arkitect\Expression\ForClasses\NotHaveNameMatching;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\*\Application\Command\*'))
    ->andThat(new HaveNameMatching('*Command'))
    ->andThat(new NotHaveNameMatching('*CommandHandler'))
    ->should(new Implement(CommandInterface::class))
    ->because('every synchronous Command must implement CommandInterface');

