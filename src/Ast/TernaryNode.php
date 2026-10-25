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

class TernaryNode implements Node
{
    /**
     * @param string $condition Condition expression
     * @param string $trueValue Value if true
     * @param string $falseValue Value if false
     * @param \Qoliber\Tsuku\Expression\Expression|null $expression Parsed condition; parsed lazily when null
     */
    public function __construct(
        public readonly string $condition,
        public readonly string $trueValue,
        public readonly string $falseValue,
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
        return $visitor->visitTernary($this);
    }
}
