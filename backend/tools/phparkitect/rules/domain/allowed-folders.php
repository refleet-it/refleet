<?php

declare(strict_types=1);

use Arkitect\Expression\ForClasses\NotResideInTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

$allowedDomainFolders = ['Event', 'Exception', 'Model', 'Policy', 'Repository', 'Specification', 'ValueObject', 'Enum', 'Interface', 'Service'];

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\*\Domain\*'))
    ->andThat(new NotResideInTheseNamespaces('App\Shared\*'))
    ->should(new ResideInOneOfTheseNamespaces(
        ...array_merge(
            array_map(static fn (string $p) => "App\\*\\Domain\\{$p}\\*", $allowedDomainFolders),
            array_map(static fn (string $p) => "App\\*\\Domain\\*\\{$p}\\*", $allowedDomainFolders),
        )
    ))
    ->because(
        <<<'TEXT'
        Domain layer may contain only these folders, each with specific purpose:
        - Model/ : Aggregate roots and domain entities with business logic
        - ValueObject/ : Immutable value objects (e.g., Email, Money, DateRange)
        - Repository/ : Repository interfaces (e.g., UserRepositoryInterface)
        - Specification/ : Business rules that check conditions (e.g., IsEligibleForDiscount)
        - Policy/ : Domain policies and access rules (e.g., CommentDeletionPolicy)
        - Event/ : Domain events emitted by aggregates (e.g., OrderPlaced)
        - Exception/ : Domain-specific exceptions (e.g., InvalidEmailException)
        - Enum/ : Enumerations (e.g., OrderStatus, Visibility)
        - Interface/ : Domain interfaces (e.g., CanBeArchived, HasOwner)
        - Service/ : Domain services when logic doesn't fit into aggregates (e.g., PricingCalculator)

        Move your file to the appropriate folder based on its purpose, or refactor if it doesn't match any category.
        TEXT
    );

