<?php

declare(strict_types=1);

use App\Shared\Domain\Event\DomainEventInterface;
use Arkitect\Expression\ForClasses\IsA;
use Arkitect\Expression\ForClasses\NotResideInTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

// IsA (not Implement) so events extending the App\Shared\Domain\Event\DomainEvent base
// class count as well — Implement only sees interfaces named in the class' own declaration.
return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\*\Domain\*\Event\*'))
    ->andThat(new NotResideInTheseNamespaces('App\Shared\*'))
    ->should(new IsA(DomainEventInterface::class))
    ->because('domain event must implement DomainEventInterface');

