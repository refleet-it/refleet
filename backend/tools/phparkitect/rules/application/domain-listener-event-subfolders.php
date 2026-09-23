<?php

declare(strict_types=1);

use Arkitect\Analyzer\ClassDescription;
use Arkitect\Expression\Description;
use Arkitect\Expression\Expression;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;
use Arkitect\Rules\Violation;
use Arkitect\Rules\Violations;

/**
 * Enforces two related conventions for domain event listeners:
 *
 * 1. Application\EventHandler\ is FORBIDDEN — use DomainListener instead.
 *
 * 2. Classes in Application\DomainListener\ MUST live in an event-named subfolder:
 *
 *      WRONG: App\Organization\Organization\Application\DomainListener\LogOrganizationCreatedWhenOrganizationCreated
 *      RIGHT: App\Organization\Organization\Application\DomainListener\OrganizationCreated\LogOrganizationCreated
 *
 * The subfolder name must match the domain event class name the listener reacts to.
 * If a listener currently handles multiple events (union type in __invoke), it must be
 * split into one handler per event, each in the correct subfolder.
 *
 * Handler class name inside the subfolder should NOT repeat the event name — the folder
 * already provides that context. E.g. inside BusinessCreated/ use LogBusinessCreated,
 * not LogBusinessCreatedWhenBusinessCreated.
 */
$domainListenerEventSubfolders = new class implements Expression {
    public function describe(ClassDescription $theClass, string $because): Description
    {
        return new Description(
            'DomainListener handlers must live in an event-named subfolder: Application\DomainListener\{EventName}\HandlerClass',
            $because,
        );
    }

    public function evaluate(ClassDescription $theClass, Violations $violations, string $because): void
    {
        $fqcn  = $theClass->getFQCN();
        $parts = \explode('\\', $fqcn);

        $appIdx = \array_search('Application', $parts, true);
        if ($appIdx === false) {
            return;
        }

        $layer     = $parts[$appIdx + 1] ?? null;
        $className = $parts[\count($parts) - 1];

        if ($layer === 'EventHandler') {
            $violations->add(new Violation(
                $fqcn,
                \sprintf(
                    '%s is in Application\\EventHandler — EventHandler is forbidden. Move to Application\\DomainListener\\{EventName}\\%s where {EventName} is the domain event class this listener handles.',
                    $className,
                    $className,
                ),
            ));

            return;
        }

        if ($layer !== 'DomainListener') {
            return;
        }

        // Segments after 'DomainListener': must have at least [{EventName}, HandlerClass]
        $afterDomainListener = \array_slice($parts, $appIdx + 2);

        if (\count($afterDomainListener) < 2) {
            $violations->add(new Violation(
                $fqcn,
                \sprintf(
                    '%s is directly in Application\\DomainListener — move to Application\\DomainListener\\{EventName}\\%s where {EventName} is the domain event class this listener handles.',
                    $className,
                    $className,
                ),
            ));
        }
    }
};

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\\'))
    ->should($domainListenerEventSubfolders)
    ->because(
        'domain event listeners must be organized by event: Application\\DomainListener\\{EventName}\\HandlerClass — '
        . 'one subfolder per domain event (named after the event class), handler inside. '
        . 'Application\\EventHandler is forbidden, use DomainListener instead. '
        . 'Handlers reacting to multiple events (union type __invoke) must be split into separate classes, one per event subfolder.'
    );
