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

class TernaryNode implements Node
{
    /**
     * @param string $condition Condition expression
     * @param string $trueValue Value if true
     * @param string $falseValue Value if false
     */
    public function __construct(
        public readonly string $condition,
        public readonly string $trueValue,
        public readonly string $falseValue,
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
        return $visitor->visitTernary($this);
    }
}
