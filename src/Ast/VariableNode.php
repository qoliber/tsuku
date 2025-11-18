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

class VariableNode implements Node
{
    public function __construct(
        public readonly string $name,
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
        return $visitor->visitVariable($this);
    }
}
