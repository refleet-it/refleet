<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class CommandFolderMustMatchDomainTest
{
    /**
     * @return iterable<Rule>
     */
    public function test_command_folders_must_match_domain(): iterable
    {
        $src = \dirname(__DIR__, 2).'/src';

        foreach ($this->contexts($src) as $context) {
            $domainDir = $src.'/'.$context.'/Domain';
            $commandDir = $src.'/'.$context.'/Application/Command';

            if (!\is_dir($commandDir)) {
                continue;
            }

            $domainNames = $this->subdirs($domainDir);

            // Check both Sync and Async subdirectories
            foreach (['Sync', 'Async'] as $syncType) {
                $syncDir = $commandDir.'/'.$syncType;
                if (!\is_dir($syncDir)) {
                    continue;
                }

                foreach ($this->subdirs($syncDir) as $commandDomain) {
                    if (!\in_array($commandDomain, $domainNames, true)) {
                        $commandDomainPath = $syncDir.'/'.$commandDomain;
                        if ($this->hasPhpFiles($commandDomainPath)) {
                            $namespace = \sprintf('App\\%s\\Application\\Command\\%s\\%s', $context, $syncType, $commandDomain);

                            yield PHPat::rule()
                                ->classes(Selector::inNamespace($namespace))
                                ->shouldNotExist()
                                ->because(
                                    <<<TEXT
                                    Folder {$context}/Application/Command/{$syncType}/{$commandDomain} has no matching {$context}/Domain/{$commandDomain}.
                                    Each folder in Application/Command/{$syncType} must correspond to an existing Domain folder with the same name.
                                    If it contains commands for a removed domain, delete it.
                                    Otherwise, move its files under {$context}/Application/Command/{$syncType}/{CorrectDomainName} that matches an existing domain.
                                    TEXT
                                );
                        }
                    }
                }
            }
        }
    }

    /**
     * @return array<string>
     */
    private function contexts(string $src): array
    {
        $globResult = \glob($src.'/*', \GLOB_ONLYDIR);

        return \array_map(basename(...), false !== $globResult ? $globResult : []);
    }

    /**
     * @return array<string>
     */
    private function subdirs(string $dir): array
    {
        if (!\is_dir($dir)) {
            return [];
        }

        $globResult = \glob($dir.'/*', \GLOB_ONLYDIR);

        return \array_map(basename(...), false !== $globResult ? $globResult : []);
    }

    private function hasPhpFiles(string $dir): bool
    {
        if (!\is_dir($dir)) {
            return false;
        }

        $phpFiles = \glob($dir.'/*.php');

        return false !== $phpFiles && [] !== $phpFiles;
    }
}
