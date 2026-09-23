<?php

declare(strict_types=1);

use Arkitect\Expression\ForClasses\NotResideInTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

// This factory returns an array of rules - one for each context
return static function (array $contexts): array {
    $rules = [];

    foreach ($contexts as $context) {
        // Rule for App\{Context}\Application\Model (base namespace and sub-namespaces)
        $rules[] = Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces(
                "App\\{$context}\\Application\\Model",
                "App\\{$context}\\Application\\Model\\*"
            ))
            ->should(new NotResideInTheseNamespaces(
                "App\\{$context}\\Application\\Model",
                "App\\{$context}\\Application\\Model\\*"
            ))
            ->because('Application/Model namespace is forbidden. DTOs must be placed next to the classes they\'re related to (Controller, Query, Command).');

        // Rule for App\{Context}\Application\ReadModel (base namespace and sub-namespaces)
        $rules[] = Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces(
                "App\\{$context}\\Application\\ReadModel",
                "App\\{$context}\\Application\\ReadModel\\*"
            ))
            ->should(new NotResideInTheseNamespaces(
                "App\\{$context}\\Application\\ReadModel",
                "App\\{$context}\\Application\\ReadModel\\*"
            ))
            ->because('Application/ReadModel namespace is forbidden. DTOs must be placed next to the classes they\'re related to (Query, Controller).');
    }

    return $rules;
};

