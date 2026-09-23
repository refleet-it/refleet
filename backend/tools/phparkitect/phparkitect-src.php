<?php

declare(strict_types=1);

use Arkitect\ClassSet;
use Arkitect\CLI\Config;

return static function (Config $config): void {
    $projectDir = __DIR__ . '/../../';
    $srcDir     = $projectDir . 'src';

    $discoverContexts = static function (string $dir): array {
        $dirs = glob($dir . '/*', GLOB_ONLYDIR) ?: [];
        return array_values(array_filter(
            array_map('basename', $dirs),
            static fn ($n) => $n !== '' && $n !== '.' && $n !== '..'
        ));
    };

    // Contexts extracted into their own application under app/ are no longer visible from
    // src/, so take them from the PSR-4 map — that is what actually defines the mapping
    // between a context namespace and its directory (docs/adr/0001-multiple-kernels.php).
    $discoverAppContexts = static function (string $projectDir): array {
        $composer = json_decode((string) file_get_contents($projectDir . 'composer.json'), true);
        $psr4     = $composer['autoload']['psr-4'] ?? [];

        $contexts = [];
        $dirs     = [];
        foreach ($psr4 as $prefix => $path) {
            if (str_starts_with((string) $path, 'app/') && preg_match('/^App\\\\([A-Za-z0-9]+)\\\\$/', (string) $prefix, $m) === 1) {
                $contexts[] = $m[1];
                $dirs[]     = $projectDir . rtrim((string) $path, '/');
            }
        }

        return [$contexts, $dirs];
    };

    [$appContexts, $appSrcDirs] = $discoverAppContexts($projectDir);

    $classSet = ClassSet::fromDir($srcDir, ...$appSrcDirs);

    $ns = static function (string $ctx, string $suffix = ''): string {
        return 'App\\' . $ctx . ($suffix !== '' ? '\\' . rtrim($suffix, '\\') . '\\*' : '\\*');
    };

    $contexts = array_merge($discoverContexts($srcDir), $appContexts);

    // Rule files are loaded through a closure so their local variables stay in the
    // closure's scope: a plain `require` here would share this function's scope, and any
    // rule file assigning $rules would wipe the accumulator built up to that point.
    $load = static fn (string $path) => require $path;

    $rules = [];

    // --- LAYER BOUNDARIES ---
    $rules[] = $load(__DIR__ . '/rules/layers/domain-independence.php');
    $rules[] = $load(__DIR__ . '/rules/layers/application-dependencies.php');
    $rules[] = $load(__DIR__ . '/rules/layers/infrastructure-dependencies.php');

    // --- APPLICATION RULES ---
    $rules[] = $load(__DIR__ . '/rules/application/no-service-namespace.php');
    $rules[] = $load(__DIR__ . '/rules/application/no-service-suffix.php');
    $rules[] = $load(__DIR__ . '/rules/application/command-sync-interface.php');
    $rules[] = $load(__DIR__ . '/rules/application/command-handler-interface.php');
    $rules[] = $load(__DIR__ . '/rules/application/domain-listener-interface.php');
    $rules[] = $load(__DIR__ . '/rules/application/handlers-readonly.php');
    $rules[] = $load(__DIR__ . '/rules/application/queries-readonly.php');
    $rules[] = $load(__DIR__ . '/rules/application/commands-readonly.php');
    // DTO location rules (per-context rules only, global rules removed to avoid duplicates)
    $dtoLocationFactory = $load(__DIR__ . '/rules/application/no-model-or-readmodel-direct.php');
    $rules = array_merge($rules, $dtoLocationFactory($contexts));

    // --- DOMAIN CONVENTIONS ---
    $rules[] = $load(__DIR__ . '/rules/domain/event-interface.php');
    $rules[] = $load(__DIR__ . '/rules/domain/value-object-final.php');
    $rules[] = $load(__DIR__ . '/rules/domain/value-object-readonly.php');
    $rules[] = $load(__DIR__ . '/rules/domain/policies-readonly.php');
    $rules[] = $load(__DIR__ . '/rules/domain/events-readonly.php');
    $rules[] = $load(__DIR__ . '/rules/domain/no-read-model.php');
    $rules[] = $load(__DIR__ . '/rules/domain/allowed-folders.php');
    $rules[] = $load(__DIR__ . '/rules/domain/layer-structure.php');

    // --- CONTEXT ISOLATION (dynamic) ---
    $contextIsolationFactory = $load(__DIR__ . '/rules/context/context-isolation.php');
    $rules = array_merge($rules, $contextIsolationFactory($contexts, $ns));

    // --- SHARED ISOLATION ---
    $sharedIsolationFactory = $load(__DIR__ . '/rules/context/shared-isolation.php');
    $sharedIsolationRule = $sharedIsolationFactory($contexts, $ns);
    if ($sharedIsolationRule !== null) {
        $rules[] = $sharedIsolationRule;
    }

    $config->add($classSet, ...$rules);

    // --- VERTICAL SLICE STRUCTURE (separate add — custom Expression must not be bundled with built-in rules) ---
    $config->add($classSet, $load(__DIR__ . '/rules/structure/no-bc-level-layers.php'));

    // --- DOMAIN LISTENER STRUCTURE (separate add — custom Expression) ---
    $config->add($classSet, $load(__DIR__ . '/rules/application/domain-listener-event-subfolders.php'));
};

