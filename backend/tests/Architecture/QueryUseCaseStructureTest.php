<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class QueryUseCaseStructureTest
{
    /**
     * Enforces:
     * 1) No classes directly under: App\<Context>\Application\Query\<Domain> (except shared DTOs)
     * 2) No classes deeper than one UseCase level under ...\Query\<Domain>\<UseCase>\*
     * 3) Naming: ...\Query\<Domain>\<UseCase>\{UseCase}(Query|Handler)
     *    DTO classes (ReadModel, Response, Request, Result, Item, DTO suffixes) are exempt
     *    from strict UseCase-prefixed naming and may use arbitrary class names.
     *
     * @return iterable<Rule>
     */
    public function test_forbid_classes_directly_under_domain(): iterable
    {
        yield PHPat::rule()
            ->classes(
                Selector::AllOf(
                    Selector::inNamespace('/^App\\\\[^\\\\]+\\\\Application\\\\Query\\\\[^\\\\]+$/', true),
                    // Allow shared DTOs directly under domain (match by suffix explicitly)
                    Selector::Not(Selector::classname('/.*(ReadModel|Response|Request|Result|Item|DTO)$/', true))
                )
            )
            ->shouldNotExist()
            ->because('Move all files from {Context}/Application/Query/{Domain} into {Context}/Application/Query/{Domain}/{UseCase}/. Shared DTOs (ReadModel, Response, Request, Result, Item, DTO suffixes) are allowed directly under domain.');
    }

    /**
     * @return iterable<Rule>
     */
    public function test_forbid_deeper_than_use_case(): iterable
    {
        yield PHPat::rule()
            ->classes(
                Selector::inNamespace('/^App\\\\[^\\\\]+\\\\Application\\\\Query\\\\[^\\\\]+\\\\[^\\\\]+\\\\[^\\\\]+/', true)
            )
            ->shouldNotExist()
            ->because('Do not nest deeper than one UseCase level under {Context}/Application/Query/{Domain}.');
    }

    /**
     * @return iterable<Rule>
     */
    public function test_forbid_query_handler_suffix(): iterable
    {
        yield PHPat::rule()
            ->classes(
                Selector::classname('/.*QueryHandler$/', true)
            )
            ->shouldNotExist()
            ->because('Handlers must be named {UseCase}Handler, not {UseCase}QueryHandler.');
    }

    /**
     * @return iterable<Rule>
     */
    public function test_enforce_use_case_naming(): iterable
    {
        yield PHPat::rule()
            ->classes(
                Selector::AllOf(
                    // exactly ...\Query\<Domain>\<UseCase>
                    Selector::inNamespace('/^App\\\\[^\\\\]+\\\\Application\\\\Query\\\\[^\\\\]+\\\\[^\\\\]+$/', true),
                    // Exclude DTOs/ReadModels/Response objects (match by suffix explicitly)
                    Selector::Not(Selector::classname('/.*(ReadModel|Response|Request|Result|Item|DTO)$/', true)),
                    // anything that does NOT match the FQCN pattern {UseCase}(Query|Handler)
                    Selector::Not(
                        Selector::classname(
                            '/^App\\\\[^\\\\]+\\\\Application\\\\Query\\\\[^\\\\]+\\\\([A-Z][A-Za-z0-9]+)\\\\\\1(?:Query|Handler)$/',
                            true
                        )
                    )
                )
            )
            ->shouldNotExist()
            ->because('Class name must be {UseCase}Query or {UseCase}Handler under {UseCase} namespace. DTOs (ReadModel, Response, Request, Result, Item, DTO suffixes) are allowed.');
    }
}
