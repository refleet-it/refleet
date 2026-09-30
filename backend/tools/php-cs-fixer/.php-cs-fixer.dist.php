<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/../../src',
        __DIR__ . '/../../app',
        __DIR__ . '/../../tests',
        __DIR__ . '/../../config',
        __DIR__ . '/../../migrations',
        __DIR__ . '/../../fixtures',
        __DIR__ . '/../../bin',
    ])
    ->exclude([
        'vendor',
        'var',
        'cache',
        'logs',
        'tests/coverage',
        'node_modules',
        '.git',
        '.idea',
        '.vscode',
        'public',
        'translations',
        'tools',
    ])
    ->ignoreVCSIgnored(true)
    ->name('*.php')
    ->notPath('#^reference\.php$#')
    ->notPath('vendor')
    ->notPath('node_modules')
    ->notPath('.git')
    ->notPath('.idea')
    ->notPath('.vscode');

$config = new PhpCsFixer\Config();

return $config
    ->setUnsupportedPhpVersionAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        // Symfony's own code does not declare strict types, so @Symfony:risky strips the
        // declaration since php-cs-fixer 3.95. This codebase relies on it everywhere.
        'declare_strict_types' => ['strategy' => 'enforce'],
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline', 'attribute_placement' => 'standalone'],
        'is_null' => true,
        'no_superfluous_elseif' => true,
        'no_useless_else' => true,
        'php_unit_set_up_tear_down_visibility' => true,
        'native_function_invocation' => [
            'include' => ['@all'],
            'scope' => 'all',
            'strict' => true,
        ],
        'php_unit_method_casing' => [
            'case' => 'snake_case',
        ],
        'ordered_class_elements' => ['order' => ['use_trait', 'constant', 'property', 'construct', 'method_public', 'method_protected', 'method_private']],
    ])
    ->setFinder($finder);
