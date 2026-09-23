<?php

declare(strict_types=1);

use App\Shared\Domain\Event\DomainEventListenerInterface;
use Arkitect\Expression\ForClasses\Implement;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\*\Application\DomainListener\*'))
    ->should(new Implement(DomainEventListenerInterface::class))
    ->because('every domain listener must implement DomainEventListenerInterface');

