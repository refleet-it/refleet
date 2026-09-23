<?php

declare(strict_types=1);

namespace Helper;

use Arkitect\Analyzer\ClassDescription;
use Arkitect\Expression\Description;
use Arkitect\Expression\Expression;
use Arkitect\Rules\Violation;
use Arkitect\Rules\Violations;

/**
 * Enforces that Domain, Application, and Infrastructure live inside a module,
 * not directly under a bounded context:
 *
 *   WRONG: App\Identity\Domain\...
 *   RIGHT: App\Identity\Account\Domain\...
 *
 * Only exception: App\Shared\* — Shared BC has no modules by design.
 */
class NoBcLevelLayerExpression implements Expression
{
    private const LAYERS = ['Domain', 'Application', 'Infrastructure'];

    private const ALLOWED_BC_PREFIXES = [
        'App\\Shared\\',
    ];

    public function describe(ClassDescription $theClass, string $because): Description
    {
        \file_put_contents('/tmp/phparkitect_debug.txt', "DESCRIBE: " . $theClass->getFQCN() . "\n", \FILE_APPEND);
        return new Description(
            'Domain/Application/Infrastructure must reside inside a module (App\\Context\\Module\\Layer), not directly under a bounded context',
            $because,
        );
    }

    public function evaluate(ClassDescription $theClass, Violations $violations, string $because): void
    {
        $fqcn  = $theClass->getFQCN();
        \file_put_contents('/tmp/phparkitect_debug.txt', "CALLED: $fqcn\n", \FILE_APPEND);
        $parts = \explode('\\', $fqcn);

        // Need at least: App \ Context \ Layer \ Something
        if (\count($parts) < 4 || $parts[0] !== 'App') {
            return;
        }

        $layer = $parts[2];

        if (!\in_array($layer, self::LAYERS, true)) {
            return;
        }

        foreach (self::ALLOWED_BC_PREFIXES as $prefix) {
            if (\str_starts_with($fqcn, $prefix)) {
                return;
            }
        }

        \file_put_contents('/tmp/phparkitect_debug.txt', $fqcn . "\n", \FILE_APPEND);
        $violations->add(
            new Violation(
                $fqcn,
                \sprintf(
                    '%s has %s directly under its bounded context — move it inside a module (App\\Context\\Module\\%s\\...)',
                    $fqcn,
                    $layer,
                    $layer,
                ),
            ),
        );
    }
}
