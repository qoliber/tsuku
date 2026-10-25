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

namespace Qoliber\Tsuku\Template;

use Qoliber\Tsuku\Ast\Parser;
use Qoliber\Tsuku\Ast\TemplateNode;
use Qoliber\Tsuku\DirectiveRegistry;
use Qoliber\Tsuku\Function\FunctionRegistry;
use Qoliber\Tsuku\Lexer\Lexer;

/**
 * Template text to AST: lexer (with whitespace control) followed by the parser.
 * Shared by Template::render() and the streaming processor so both see the same tree.
 */
final class TemplateParser
{
    public function __construct(
        private readonly ?FunctionRegistry $functionRegistry = null,
        private readonly ?DirectiveRegistry $directiveRegistry = null,
    ) {
    }

    /**
     * @param string $template
     * @return \Qoliber\Tsuku\Ast\TemplateNode
     * @throws \Qoliber\Tsuku\Exception\ParseException
     * @throws \Qoliber\Tsuku\Exception\FunctionNotFoundException
     */
    public function parse(string $template): TemplateNode
    {
        $lexer = new Lexer($template, $this->directiveRegistry?->names() ?? []);
        $tokens = $lexer->tokenize();

        return (new Parser($tokens, $lexer->getSourceMap(), $this->functionRegistry))->parse();
    }
}
