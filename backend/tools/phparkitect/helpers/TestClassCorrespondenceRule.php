<?php

declare(strict_types=1);

namespace Helper;

use Arkitect\Analyzer\ClassDescription;
use Arkitect\Expression\Description;
use Arkitect\Expression\Expression;
use Arkitect\Rules\Violation;
use Arkitect\Rules\Violations;

class TestClassCorrespondenceRule implements Expression
{
    /** @var array<string, string>|null */
    private static ?array $psr4 = null;

    public function describe(ClassDescription $theClass, string $because): Description
    {
        return new Description(
            \sprintf(
                'Test class %s must have a corresponding production class.',
                $theClass->getFQCN()
            ),
            $because
        );
    }

    public function evaluate(ClassDescription $theClass, Violations $violations, string $because): void
    {
        $fullTestClassName = $theClass->getFQCN();

        // Skip helper/fixture classes that don't end with "Test"
        if (!\str_ends_with($fullTestClassName, 'Test')) {
            return;
        }

        $srcClassName = \str_replace(['App\Tests\Unit\\', 'App\Tests\Integration\\'], 'App\\', $fullTestClassName);

        if (\str_ends_with($srcClassName, 'Test')) {
            $srcClassName = \substr($srcClassName, 0, -4);
        }

        foreach ($this->candidates($srcClassName) as $candidate) {
            if (null !== $this->classFile($candidate)) {
                return;
            }
        }

        $violations->add(new Violation(
            '0',
            \sprintf(
                'Missing corresponding class %s for test %s, because %s.',
                $srcClassName,
                $fullTestClassName,
                $because
            )
        ));
    }

    /**
     * Class names the production counterpart is allowed to have, beyond the literal one.
     *
     * @return list<string>
     */
    private function candidates(string $className): array
    {
        $candidates = [$className, $className.'Interface'];

        // EmployeeRepositoryInterface -> EmployeeRepository
        if (\str_ends_with($className, 'Interface')) {
            $candidates[] = \substr($className, 0, -9);
        }

        $parts = \explode('\\', $className);
        $shortName = \array_pop($parts);
        $namespace = \implode('\\', $parts);

        // Aggregates: Domain\Waiter\Waiter -> Domain\Waiter\Model\Waiter
        $candidates[] = $namespace.'\Model\\'.$shortName;

        // CQRS handlers: Command\CreateAccountHandler -> Command\CreateAccount\CreateAccountHandler
        if (\str_ends_with($shortName, 'Handler')) {
            $candidates[] = $namespace.'\\'.\substr($shortName, 0, -7).'\\'.$shortName;
        }

        // Read models sit one level up: Model\BusinessRead\BusinessReadModel -> Model\BusinessReadModel
        if (\str_ends_with($shortName, 'ReadModel') && \count($parts) > 0) {
            $candidates[] = \implode('\\', \array_slice($parts, 0, -1)).'\\'.$shortName;
        }

        return $candidates;
    }

    /**
     * Resolves a class to a file through the PSR-4 map, so a context keeps validating after
     * it moves out of src/ into its own application under app/.
     */
    private function classFile(string $fqcn): ?string
    {
        $matchedPrefix = '';
        $matchedDir = null;

        foreach ($this->psr4() as $prefix => $dir) {
            if (\str_starts_with($fqcn.'\\', $prefix) && \strlen($prefix) > \strlen($matchedPrefix)) {
                $matchedPrefix = $prefix;
                $matchedDir = $dir;
            }
        }

        if (null === $matchedDir) {
            return null;
        }

        $relative = \str_replace('\\', '/', \substr($fqcn, \strlen($matchedPrefix)));
        $path = __DIR__.'/../../../'.$matchedDir.$relative.'.php';

        return \file_exists($path) ? $path : null;
    }

    /**
     * @return array<string, string>
     */
    private function psr4(): array
    {
        if (null !== self::$psr4) {
            return self::$psr4;
        }

        /** @var array{autoload?: array{psr-4?: array<string, string>}} $composer */
        $composer = \json_decode((string) \file_get_contents(__DIR__.'/../../../composer.json'), true);

        return self::$psr4 = $composer['autoload']['psr-4'] ?? [];
    }
}
