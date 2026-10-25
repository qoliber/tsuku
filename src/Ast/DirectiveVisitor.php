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
 * Visitors that can render custom block directives. Kept separate from
 * NodeVisitor so existing NodeVisitor implementations stay valid.
 */
interface DirectiveVisitor
{
    /**
     * Visit a custom directive node
     *
     * @param \Qoliber\Tsuku\Ast\DirectiveNode $node
     * @return mixed
     */
    public function visitDirective(DirectiveNode $node): mixed;
}
