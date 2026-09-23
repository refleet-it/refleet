<?php

declare(strict_types=1);

namespace Rector\Custom\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Type\ObjectType;
use PHPUnit\Framework\Assert;
use Rector\PHPUnit\NodeAnalyzer\TestsNodeAnalyzer;
use Rector\Rector\AbstractRector;

final class MethodAssertionsToPHPUnitAssertStaticMethodRector extends AbstractRector
{
    /**
     * @var string
     */
    private $assertClass;
    /**
     * @readonly
     *
     * @var TestsNodeAnalyzer
     */
    private $testsNodeAnalyzer;

    public function __construct(TestsNodeAnalyzer $testsNodeAnalyzer)
    {
        $this->testsNodeAnalyzer = $testsNodeAnalyzer;
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param Class_ $node
     */
    public function refactor(Node $node): ?Node
    {
        if (!$this->testsNodeAnalyzer->isInTestClass($node)) {
            return null;
        }
        $hasChanged = \false;
        $this->traverseNodesWithCallable($node, function (Node $node) use (&$hasChanged): ?StaticCall {
            if (!$node instanceof MethodCall) {
                return null;
            }
            $methodName = $this->getName($node->name);
            if (!\is_string($methodName)) {
                return null;
            }
            if (0 !== \strncmp($methodName, 'assert', \strlen('assert'))) {
                return null;
            }
            if (!$this->isName($node->var, 'this')) {
                return null;
            }
            if (!$this->isObjectType($node->var, new ObjectType(PHPUnit\Framework\TestCase::class))) {
                return null;
            }

            $assert = new \ReflectionClass(Assert::class);
            if (!($assert->hasMethod($methodName) && $assert->getMethod($methodName)->isStatic())) {
                return null;
            }
            $hasChanged = \true;

            return $this->nodeFactory->createStaticCall(Assert::class, $methodName, $node->getArgs());
        });

        if ($hasChanged) {
            return $node;
        }

        return null;
    }

    #[\Override]
    public function getRuleDefinition(): \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
    {
        return new \Symplify\RuleDocGenerator\ValueObject\RuleDefinition('Convert method call assertions in classes extended TestCase to static call from PHPUnit Assert object', []);
    }
}
