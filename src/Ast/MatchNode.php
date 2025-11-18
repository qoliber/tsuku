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

class MatchNode implements Node
{
    /**
     * @param string $expression Expression to match against
     * @param array<\Qoliber\Tsuku\Ast\CaseNode> $cases Case nodes to evaluate
     * @param array<\Qoliber\Tsuku\Ast\Node> $defaultChildren Default children if no case matches
     */
    public function __construct(
        public readonly string $expression,
        public readonly array $cases = [],
        public readonly array $defaultChildren = [],
    ) {
    }

    public function accept(NodeVisitor $visitor): string
    {
        return $visitor->visitMatch($this);
    }
}
