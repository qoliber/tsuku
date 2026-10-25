<?php

/**
 * Tsuku - Transform data into any format
 *
 * @package   Qoliber\Tsuku
 * @author    Jakub Winkler <jwinkler@qoliber.com>
 * @copyright 2025 qoliber
 * @license   MIT
 */

declare(strict_types=1);

namespace Qoliber\Tsuku\Ast;

use Qoliber\Tsuku\Expression\Expression;

class IfNode implements Node
{
    /**
     * @param string $condition Condition expression
     * @param bool $negate Whether to negate condition (unless)
     * @param array<\Qoliber\Tsuku\Ast\Node> $children Child nodes to execute if true
     * @param array<\Qoliber\Tsuku\Ast\Node> $elseChildren Child nodes to execute if false
     *        (an @elseif chain is a single nested IfNode)
     * @param \Qoliber\Tsuku\Expression\Expression|null $expression Parsed condition; parsed lazily when null
     */
    public function __construct(
        public readonly string $condition,
        public readonly bool $negate,
        public readonly array $children,
        public readonly array $elseChildren = [],
        public readonly ?Expression $expression = null,
    ) {
    }

    /**
     * Accept a visitor
     *
     * @param \Qoliber\Tsuku\Ast\NodeVisitor $visitor
     * @return mixed
     */
    public function accept(NodeVisitor $visitor): mixed
    {
        return $visitor->visitIf($this);
    }
}
