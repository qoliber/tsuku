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

/**
 * Custom block directive: @name(arguments) ... @end
 */
class DirectiveNode implements Node
{
    /**
     * @param string $name Directive name without "@"
     * @param array<int, string> $arguments Argument source texts
     * @param array<\Qoliber\Tsuku\Ast\Node> $children Body
     */
    public function __construct(
        public readonly string $name,
        public readonly array $arguments,
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
        if (!$visitor instanceof DirectiveVisitor) {
            throw new \LogicException(sprintf(
                '%s cannot render the custom directive @%s; implement %s',
                get_class($visitor),
                $this->name,
                DirectiveVisitor::class
            ));
        }

        return $visitor->visitDirective($this);
    }
}
