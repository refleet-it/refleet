<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class QueryFolderMustMatchDomainTest
{
    /**
     * @return iterable<Rule>
     */
    public function test_query_folders_must_match_domain(): iterable
    {
        $src = \dirname(__DIR__, 2).'/src';

        foreach ($this->contexts($src) as $context) {
            $domainDir = $src.'/'.$context.'/Domain';
            $queryDir = $src.'/'.$context.'/Application/Query';

            if (!\is_dir($queryDir)) {
                continue;
            }

            $domainNames = $this->subdirs($domainDir);

            foreach ($this->subdirs($queryDir) as $queryDomain) {
                if (!\in_array($queryDomain, $domainNames, true)) {
                    $queryDomainPath = $queryDir.'/'.$queryDomain;
                    if ($this->hasPhpFiles($queryDomainPath)) {
                        $namespace = \sprintf('App\\%s\\Application\\Query\\%s', $context, $queryDomain);

                        yield PHPat::rule()
                            ->classes(Selector::inNamespace($namespace))
                            ->shouldNotExist()
                            ->because(
                                <<<TEXT
                                Folder {$context}/Application/Query/{$queryDomain} has no matching {$context}/Domain/{$queryDomain}.
                                Each folder in Application/Query must correspond to an existing Domain folder with the same name.
                                If it contains queries for a removed domain, delete it.
                                Otherwise, move its files under {$context}/Application/Query/{CorrectDomainName} that matches an existing domain.
                                TEXT
                            );
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
