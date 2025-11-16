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

class ForNode implements Node
{
    /**
     * @param string $collection Collection variable name
     * @param string $itemVar Item variable name
     * @param string|null $keyVar Optional key variable name
     * @param array<\Qoliber\Tsuku\Ast\Node> $children Child nodes to execute per iteration
     */
    public function __construct(
        public readonly string $collection,
        public readonly string $itemVar,
        public readonly ?string $keyVar,
        public readonly array $children,
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
        return $visitor->visitFor($this);
    }
}
