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

class CaseNode implements Node
{
    /**
     * @param array<string> $values Values to match against
     * @param array<\Qoliber\Tsuku\Ast\Node> $children Child nodes to execute if matched
     */
    public function __construct(
        public readonly array $values,
        public readonly array $children = [],
    ) {
    }

    public function accept(NodeVisitor $visitor): string
    {
        return $visitor->visitCase($this);
    }
}
