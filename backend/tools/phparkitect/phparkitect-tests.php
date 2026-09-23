<?php

declare(strict_types=1);

use Arkitect\ClassSet;
use Arkitect\CLI\Config;

return static function (Config $config): void {
    $testsDir = __DIR__ . '/../../tests';

    // Check if tests directory exists
    if (!is_dir($testsDir)) {
        return;
    }

    // Contexts keep their tests next to their sources under app/<context>/tests
    $appTestDirs = glob(__DIR__ . '/../../app/*/tests', GLOB_ONLYDIR) ?: [];

    $classSet = ClassSet::fromDir($testsDir, ...$appTestDirs)
        ->excludePath('Architecture');
    $rules = [];

    // --- TEST RULES ---
    // 1. All tests must be in tests/Unit namespace
    $rules[] = require __DIR__ . '/rules/tests/unit-namespace-only.php';

    // 2. Test class path must correspond to production class path in src
    $rules[] = require __DIR__ . '/rules/tests/test-class-correspondence.php';

    $config->add($classSet, ...$rules);
};
