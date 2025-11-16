<?php

declare(strict_types=1);

/**
 * Tsuku - Transform data into any format
 *
 * @package   Qoliber\Tsuku
 * @author    Jakub Winkler <jwinkler@qoliber.com>
 * @copyright 2024 Qoliber
 * @license   MIT
 */

namespace Qoliber\Tsuku\Ast;

class IfNode implements Node
{
    /**
     * @param string $condition Condition expression
     * @param bool $negate Whether to negate condition (unless)
     * @param array<\Qoliber\Tsuku\Ast\Node> $children Child nodes to execute if true
     * @param array<\Qoliber\Tsuku\Ast\Node> $elseChildren Child nodes to execute if false
     */
    public function __construct(
        public readonly string $condition,
        public readonly bool $negate,
        public readonly array $children,
        public readonly array $elseChildren = [],
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
