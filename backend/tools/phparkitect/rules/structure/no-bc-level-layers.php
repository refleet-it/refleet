<?php

declare(strict_types=1);

use Arkitect\Analyzer\ClassDescription;
use Arkitect\Expression\Description;
use Arkitect\Expression\Expression;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;
use Arkitect\Rules\Violation;
use Arkitect\Rules\Violations;

$noBcLevelLayer = new class implements Expression {
    private const LAYERS = ['Domain', 'Application', 'Infrastructure'];

    public function describe(ClassDescription $theClass, string $because): Description
    {
        return new Description(
            'Domain/Application/Infrastructure must reside inside a module (App\\Context\\Module\\Layer), not directly under a bounded context',
            $because,
        );
    }

    public function evaluate(ClassDescription $theClass, Violations $violations, string $because): void
    {
        $fqcn  = $theClass->getFQCN();
        $parts = \explode('\\', $fqcn);

        // Need: App \ Context \ Layer \ ... (4+ parts, Layer at index 2)
        if (\count($parts) < 4 || $parts[0] !== 'App') {
            return;
        }

        // App\Shared\* is exempt — Shared has no modules by design
        if ($parts[1] === 'Shared') {
            return;
        }

        if (!\in_array($parts[2], self::LAYERS, true)) {
            return;
        }

        $violations->add(
            new Violation(
                $fqcn,
                \sprintf(
                    '%s has %s directly under the bounded context — move it to App\\%s\\{Module}\\%s\\...',
                    $fqcn,
                    $parts[2],
                    $parts[1],
                    $parts[2],
                ),
            ),
        );
    }
};

return Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\\'))
    ->should($noBcLevelLayer)
    ->because('Domain, Application and Infrastructure must live inside a module (App\Context\Module\Layer), not directly under a bounded context (App\Context\Layer)');
