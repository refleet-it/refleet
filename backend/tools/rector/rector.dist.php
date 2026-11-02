<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php83\Rector\Class_\ReadOnlyAnonymousClassRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/../../src',
        __DIR__.'/../../app',
        __DIR__.'/../../tests',
        __DIR__.'/../../migrations',
    ])
    ->withoutParallel()
    ->withFileExtensions(['php'])
    ->withPhpSets(php85: true)
    ->withPreparedSets(
        codeQuality: true,
        codingStyle: true,
        privatization: true,
        instanceOf: true,
        phpunitCodeQuality: true,
    )->withSets([
        Rector\PHPUnit\Set\PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,
        Rector\PHPUnit\Set\PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        // Replaces the version-numbered sets (PHPUNIT_100, PHPUNIT_120), which rector 2.7
        // dropped: COMPOSER_BASED reads the installed PHPUnit version out of composer.json
        // and applies the migrations for it, so the set no longer has to be restated here
        // every time PHPUnit moves.
        Rector\PHPUnit\Set\PHPUnitSetList::COMPOSER_BASED,
    ])
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
    ])
    ->withConfiguredRule(AddOverrideAttributeToOverriddenMethodsRector::class, [
        AddOverrideAttributeToOverriddenMethodsRector::ALLOW_OVERRIDE_EMPTY_METHOD => true,
    ])
    ->withSkip([
        // Working null-check idiom the project already enforces via PHP CS Fixer's `is_null`
        // rule; instanceof would replace it with the declared (often less specific) type.
        Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector::class,
        // PHPMD's PDepend parser cannot parse PHP 8.4's "new X()->method()" without
        // parentheses, nor PHP 8.3's "new readonly class(...)" — both hard-crash the
        // parser rather than reporting a rule violation.
        Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector::class,
        ReadOnlyAnonymousClassRector::class,
    ]);
