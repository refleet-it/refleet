<?php

declare(strict_types=1);

use Arkitect\ClassSet;
use Arkitect\CLI\Config;

return static function (Config $config): void {
    $srcDir   = __DIR__ . '/../../src';
    $classSet = ClassSet::fromDir($srcDir);

    $discoverContexts = static function (string $dir): array {
        $dirs = glob($dir . '/*', GLOB_ONLYDIR) ?: [];
        return array_values(array_filter(
            array_map('basename', $dirs),
            static fn ($n) => $n !== '' && $n !== '.' && $n !== '..'
        ));
    };

    $contexts = $discoverContexts($srcDir);
    
    $dtoLocationFactory = require __DIR__ . '/rules/application/no-model-or-readmodel-direct.php';
    $rules = $dtoLocationFactory($contexts);

    $config->add($classSet, ...$rules);
};
