<?php

declare(strict_types=1);

use App\Shared\Application\Command\Sync\CommandHandlerInterface;
use Arkitect\Expression\ForClasses\HaveNameMatching;
use Arkitect\Expression\ForClasses\Implement;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\*\Application\Command\Sync\*'))
    ->andThat(new HaveNameMatching('*Handler'))
    ->should(new Implement(CommandHandlerInterface::class))
    ->because('every synchronous Handler must implement CommandHandlerInterface');

