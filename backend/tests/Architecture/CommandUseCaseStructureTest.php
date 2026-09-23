<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class CommandUseCaseStructureTest
{
    /**
     * Enforces:
     * 0) Only Sync and Async folders directly under Command
     * 1) No classes directly under: App\<Context>\Application\Command\<Sync|Async>\<Domain>
     * 2) No classes deeper than one UseCase level under ...\Command\<Sync|Async>\<Domain>\<UseCase>\*
     * 3) Naming: ...\Command\<Sync|Async>\<Domain>\<UseCase>\{UseCase}(Command|Handler)
     *
     * @return iterable<Rule>
     */
    public function test_forbid_classes_without_sync_or_async(): iterable
    {
        yield PHPat::rule()
            ->classes(
                Selector::AllOf(
                    Selector::inNamespace('/^App\\\\[^\\\\]+\\\\Application\\\\Command\\\\[^\\\\]+/', true),
                    Selector::Not(Selector::inNamespace('/^App\\\\[^\\\\]+\\\\Application\\\\Command\\\\(Sync|Async)/', true))
                )
            )
            ->shouldNotExist()
            ->because('All commands must be placed under {Context}/Application/Command/Sync or {Context}/Application/Command/Async. Move files from {Context}/Application/Command/{FolderName} into either Sync or Async subfolder.');
    }

    /**
     * @return iterable<Rule>
     */
    public function test_forbid_classes_directly_under_domain(): iterable
    {
        yield PHPat::rule()
            ->classes(
                Selector::inNamespace('/^App\\\\[^\\\\]+\\\\Application\\\\Command\\\\(Sync|Async)\\\\[^\\\\]+$/', true)
            )
            ->shouldNotExist()
            ->because('Move all files from {Context}/Application/Command/{Sync|Async}/{Domain} into {Context}/Application/Command/{Sync|Async}/{Domain}/{UseCase}/{UseCase}Command or {UseCase}Handler.');
    }

    /**
     * @return iterable<Rule>
     */
    public function test_forbid_deeper_than_use_case(): iterable
    {
        yield PHPat::rule()
            ->classes(
                Selector::inNamespace('/^App\\\\[^\\\\]+\\\\Application\\\\Command\\\\(Sync|Async)\\\\[^\\\\]+\\\\[^\\\\]+\\\\[^\\\\]+/', true)
            )
            ->shouldNotExist()
            ->because('Do not nest deeper than one UseCase level under {Context}/Application/Command/{Sync|Async}/{Domain}.');
    }

    /**
     * @return iterable<Rule>
     */
    public function test_forbid_command_handler_suffix(): iterable
    {
        yield PHPat::rule()
            ->classes(
                Selector::classname('/.*CommandHandler$/', true)
            )
            ->shouldNotExist()
            ->because('Handlers must be named {UseCase}Handler, not {UseCase}CommandHandler.');
    }

    /**
     * @return iterable<Rule>
     */
    public function test_enforce_use_case_naming(): iterable
    {
        yield PHPat::rule()
            ->classes(
                Selector::AllOf(
                    // exactly ...\Command\<Sync|Async>\<Domain>\<UseCase>
                    Selector::inNamespace('/^App\\\\[^\\\\]+\\\\Application\\\\Command\\\\(Sync|Async)\\\\[^\\\\]+\\\\[^\\\\]+$/', true),
                    // anything that does NOT match the FQCN pattern {UseCase}(Command|Handler)
                    Selector::Not(
                        Selector::classname(
                            '/^App\\\\[^\\\\]+\\\\Application\\\\Command\\\\(Sync|Async)\\\\[^\\\\]+\\\\([A-Z][A-Za-z0-9]+)\\\\\\2(?:Command|Handler)$/',
                            true
                        )
                    )
                )
            )
            ->shouldNotExist()
            ->because('Class name must be {UseCase}Command or {UseCase}Handler under {UseCase} namespace.');
    }
}
