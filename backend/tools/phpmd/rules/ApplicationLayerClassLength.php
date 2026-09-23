<?php

declare(strict_types=1);

namespace Refleet\PHPMD\Rule;

use PHPMD\AbstractNode;
use PHPMD\AbstractRule;
use PHPMD\Rule\ClassAware;

class ApplicationLayerClassLength extends AbstractRule implements ClassAware
{
    public function apply(AbstractNode $node): void
    {
        if (!str_contains($node->getFileName(), '/Application/')) {
            return;
        }

        $threshold = $this->getIntProperty('minimum');
        $loc = $node->getMetric('loc');

        if ($loc < $threshold) {
            return;
        }

        $this->addViolation($node, [$node->getName(), $loc, $threshold]);
    }
}
