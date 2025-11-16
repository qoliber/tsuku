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

interface NodeVisitor
{
    /**
     * Visit a template node
     *
     * @param \Qoliber\Tsuku\Ast\TemplateNode $node
     * @return mixed
     */
    public function visitTemplate(TemplateNode $node): mixed;

    /**
     * Visit a text node
     *
     * @param \Qoliber\Tsuku\Ast\TextNode $node
     * @return mixed
     */
    public function visitText(TextNode $node): mixed;

    /**
     * Visit a variable node
     *
     * @param \Qoliber\Tsuku\Ast\VariableNode $node
     * @return mixed
     */
    public function visitVariable(VariableNode $node): mixed;

    /**
     * Visit a for loop node
     *
     * @param \Qoliber\Tsuku\Ast\ForNode $node
     * @return mixed
     */
    public function visitFor(ForNode $node): mixed;

    /**
     * Visit an if node
     *
     * @param \Qoliber\Tsuku\Ast\IfNode $node
     * @return mixed
     */
    public function visitIf(IfNode $node): mixed;

    /**
     * Visit a ternary node
     *
     * @param \Qoliber\Tsuku\Ast\TernaryNode $node
     * @return mixed
     */
    public function visitTernary(TernaryNode $node): mixed;

    /**
     * Visit a function node
     *
     * @param \Qoliber\Tsuku\Ast\FunctionNode $node
     * @return mixed
     */
    public function visitFunction(FunctionNode $node): mixed;

    /**
     * Visit a match node
     *
     * @param \Qoliber\Tsuku\Ast\MatchNode $node
     * @return mixed
     */
    public function visitMatch(MatchNode $node): mixed;

    /**
     * Visit a case node
     *
     * @param \Qoliber\Tsuku\Ast\CaseNode $node
     * @return mixed
     */
    public function visitCase(CaseNode $node): mixed;
}
