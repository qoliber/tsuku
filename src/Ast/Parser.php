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

use Qoliber\Tsuku\Exception\ParseException;
use Qoliber\Tsuku\Expression\ExpressionParser;
use Qoliber\Tsuku\Function\FunctionRegistry;
use Qoliber\Tsuku\Lexer\SourceMap;
use Qoliber\Tsuku\Lexer\Token;
use Qoliber\Tsuku\Lexer\TokenType;
use Qoliber\Tsuku\Support\Syntax;

class Parser
{
    private int $current = 0;

    private readonly ExpressionParser $expressions;

    /**
     * @param array<\Qoliber\Tsuku\Lexer\Token> $tokens Tokens ending with EOF (Lexer::tokenize())
     * @param \Qoliber\Tsuku\Lexer\SourceMap|null $sourceMap Exact error positions inside conditions
     * @param \Qoliber\Tsuku\Function\FunctionRegistry|null $functionRegistry When given, unknown
     *        functions in conditions are rejected while parsing (otherwise when evaluated)
     */
    public function __construct(
        private readonly array $tokens,
        private readonly ?SourceMap $sourceMap = null,
        ?FunctionRegistry $functionRegistry = null,
    ) {
        $this->expressions = new ExpressionParser($functionRegistry);
    }

    /**
     * Parse tokens into AST
     *
     * @return \Qoliber\Tsuku\Ast\TemplateNode
     * @throws \Qoliber\Tsuku\Exception\ParseException
     */
    public function parse(): TemplateNode
    {
        $children = [];

        while (!$this->isAtEnd()) {
            $children[] = $this->parseNode();
        }

        return new TemplateNode($children);
    }

    /**
     * Parse a single node
     *
     * @return \Qoliber\Tsuku\Ast\Node
     * @throws \Qoliber\Tsuku\Exception\ParseException
     */
    private function parseNode(): Node
    {
        $token = $this->peek();

        return match ($token->type) {
            TokenType::TEXT => new TextNode($this->advance()->value),
            TokenType::VARIABLE => new VariableNode($this->advance()->value),
            TokenType::TERNARY => $this->parseTernary(),
            TokenType::FUNCTION => $this->parseFunction(),
            TokenType::DIRECTIVE_FOR => $this->parseFor(),
            TokenType::DIRECTIVE_IF => $this->parseIf(false),
            TokenType::DIRECTIVE_UNLESS => $this->parseIf(true),
            TokenType::DIRECTIVE_MATCH => $this->parseMatch(),
            TokenType::DIRECTIVE_CUSTOM => $this->parseDirective(),
            TokenType::DIRECTIVE_ELSE => throw $this->error(
                'Unexpected @else',
                $token,
                ' - @else must follow @if or @unless'
            ),
            TokenType::DIRECTIVE_ELSEIF => throw $this->error(
                'Unexpected @elseif',
                $token,
                ' - @elseif must follow @if or @unless'
            ),
            TokenType::DIRECTIVE_CASE => throw $this->error(
                'Unexpected @case',
                $token,
                ' - @case must be inside @match'
            ),
            TokenType::DIRECTIVE_DEFAULT => throw $this->error(
                'Unexpected @default',
                $token,
                ' - @default must be inside @match'
            ),
            TokenType::DIRECTIVE_END => throw $this->error('Unexpected @end', $token),
            TokenType::EOF => throw $this->error('Unexpected end of template', $token),
        };
    }

    /**
     * Parse @?{condition "true" : "false"} (also "condition ? true : false")
     *
     * @return \Qoliber\Tsuku\Ast\TernaryNode
     * @throws \Qoliber\Tsuku\Exception\ParseException
     */
    private function parseTernary(): TernaryNode
    {
        $token = $this->advance();
        $inline = $this->expressions->parseInlineCondition($token->value, $this->positions($token));

        return new TernaryNode($inline->condition, $inline->trueValue, $inline->falseValue, $inline->expression);
    }

    /**
     * Parse function call node; token value format: "functionName|args"
     *
     * @return \Qoliber\Tsuku\Ast\FunctionNode
     */
    private function parseFunction(): FunctionNode
    {
        [$name, $arguments] = $this->nameAndArguments($this->advance());

        return new FunctionNode($name, $arguments);
    }

    /**
     * Parse a registered block directive: @name(args) ... @end
     *
     * @return \Qoliber\Tsuku\Ast\DirectiveNode
     * @throws \Qoliber\Tsuku\Exception\ParseException
     */
    private function parseDirective(): DirectiveNode
    {
        $token = $this->advance();
        [$name, $arguments] = $this->nameAndArguments($token);

        return new DirectiveNode($name, $arguments, $this->parseUntilEnd($token, '@' . $name));
    }

    /**
     * @param \Qoliber\Tsuku\Lexer\Token $token
     * @return array{0: string, 1: array<int, string>}
     */
    private function nameAndArguments(Token $token): array
    {
        $parts = explode('|', $token->value, 2);
        $arguments = $parts[1] ?? '';

        return [$parts[0], trim($arguments) === '' ? [] : Syntax::splitArguments($arguments)];
    }

    /**
     * Parse for loop node
     *
     * @return \Qoliber\Tsuku\Ast\ForNode
     * @throws \Qoliber\Tsuku\Exception\ParseException
     */
    private function parseFor(): ForNode
    {
        $token = $this->advance();
        $args = $token->value;

        // "items as item" or "items as item, key"
        if (!preg_match('/^\s*(\w+(?:\.\w+)*)\s+as\s+(\w+)(?:\s*,\s*(\w+))?\s*$/i', $args, $matches)) {
            throw $this->error(
                'Invalid @for syntax',
                $token,
                ": '{$args}'. Expected: 'items as item' or 'items as item, key'"
            );
        }

        return new ForNode($matches[1], $matches[2], $matches[3] ?? null, $this->parseUntilEnd($token, '@for'));
    }

    /**
     * Parse @if/@unless (and a chain of @elseif)
     *
     * @param bool $negate Whether this is an unless
     * @return \Qoliber\Tsuku\Ast\IfNode
     * @throws \Qoliber\Tsuku\Exception\ParseException
     */
    private function parseIf(bool $negate): IfNode
    {
        $token = $this->advance();
        $expression = $this->expressions->parseCondition($token->value, $this->positions($token));
        $directive = $negate ? '@unless' : '@if';

        $children = $this->parseUntil([TokenType::DIRECTIVE_ELSE, TokenType::DIRECTIVE_ELSEIF]);

        $elseChildren = [];
        $next = $this->peek();
        if ($next->type === TokenType::DIRECTIVE_ELSE) {
            $this->advance();
            $elseChildren = $this->parseUntilEnd($token, $directive);
        } elseif ($next->type === TokenType::DIRECTIVE_ELSEIF) {
            // The nested @if consumes the remaining branches and the single @end
            $elseChildren = [$this->parseIf(false)];
        } elseif ($next->type === TokenType::DIRECTIVE_END) {
            $this->advance();
        } else {
            throw $this->unclosed($token, $directive, 'Expected @else or @end');
        }

        return new IfNode(trim($token->value), $negate, $children, $elseChildren, $expression);
    }

    /**
     * Parse @match directive
     *
     * @return \Qoliber\Tsuku\Ast\MatchNode
     * @throws \Qoliber\Tsuku\Exception\ParseException
     */
    private function parseMatch(): MatchNode
    {
        $token = $this->advance();
        $branches = [TokenType::DIRECTIVE_CASE, TokenType::DIRECTIVE_DEFAULT];
        $cases = [];
        $defaultChildren = [];

        while (!$this->isAtEnd() && $this->peek()->type !== TokenType::DIRECTIVE_END) {
            $current = $this->peek();

            // Whitespace before the first @case is never output
            if ($current->type === TokenType::TEXT && trim($current->value) === '') {
                $this->advance();
                continue;
            }

            if ($current->type === TokenType::DIRECTIVE_CASE) {
                $this->advance();
                $cases[] = new CaseNode(Syntax::splitArguments(trim($current->value)), $this->parseUntil($branches));
            } elseif ($current->type === TokenType::DIRECTIVE_DEFAULT) {
                $this->advance();
                $defaultChildren = $this->parseUntil($branches);
                break; // @default must be last
            } else {
                throw $this->error('Expected @case or @default inside @match', $current);
            }
        }

        if ($this->peek()->type !== TokenType::DIRECTIVE_END) {
            throw $this->unclosed($token, '@match', 'Expected @end');
        }
        $this->advance();

        return new MatchNode(trim($token->value), $cases, $defaultChildren);
    }

    /**
     * Parse nodes until @end or one of the given directives (not consumed)
     *
     * @param array<int, \Qoliber\Tsuku\Lexer\TokenType> $stopAt
     * @return array<\Qoliber\Tsuku\Ast\Node>
     * @throws \Qoliber\Tsuku\Exception\ParseException
     */
    private function parseUntil(array $stopAt): array
    {
        $children = [];

        while (!$this->isAtEnd()) {
            $type = $this->peek()->type;
            if ($type === TokenType::DIRECTIVE_END || in_array($type, $stopAt, true)) {
                break;
            }
            $children[] = $this->parseNode();
        }

        return $children;
    }

    /**
     * Parse nodes until @end and consume it
     *
     * @param \Qoliber\Tsuku\Lexer\Token $opener
     * @param string $directive
     * @return array<\Qoliber\Tsuku\Ast\Node>
     * @throws \Qoliber\Tsuku\Exception\ParseException
     */
    private function parseUntilEnd(Token $opener, string $directive): array
    {
        $children = $this->parseUntil([]);

        if ($this->peek()->type !== TokenType::DIRECTIVE_END) {
            throw $this->unclosed($opener, $directive, 'Expected @end');
        }
        $this->advance();

        return $children;
    }

    /**
     * Map offsets inside a token value to template positions.
     *
     * @param \Qoliber\Tsuku\Lexer\Token $token
     * @return callable(int): array{0: int, 1: int}
     */
    private function positions(Token $token): callable
    {
        $sourceMap = $this->sourceMap;
        if ($sourceMap !== null && $token->valueOffset >= 0) {
            return static fn(int $offset): array => $sourceMap->position($token->valueOffset + $offset);
        }

        return static fn(int $offset): array => [$token->line, $token->column + $offset];
    }

    /**
     * @param string $message
     * @param \Qoliber\Tsuku\Lexer\Token $token
     * @param string $suffix
     * @return \Qoliber\Tsuku\Exception\ParseException
     */
    private function error(string $message, Token $token, string $suffix = ''): ParseException
    {
        return ParseException::at(
            "{$message} at line {$token->line}, column {$token->column}{$suffix}",
            $token->line,
            $token->column
        );
    }

    /**
     * @param \Qoliber\Tsuku\Lexer\Token $opener
     * @param string $directive
     * @param string $expected
     * @return \Qoliber\Tsuku\Exception\ParseException
     */
    private function unclosed(Token $opener, string $directive, string $expected): ParseException
    {
        $found = $this->peek();
        $what = $found->type === TokenType::EOF
            ? 'reached end of file'
            : 'found @' . strtolower(substr($found->type->value, strlen('DIRECTIVE_')));

        return ParseException::at(
            "{$expected} but {$what} at line {$found->line}, column {$found->column}"
            . " ({$directive} opened at line {$opener->line}, column {$opener->column})",
            $found->line,
            $found->column
        );
    }

    /**
     * Get current token without consuming
     *
     * @return \Qoliber\Tsuku\Lexer\Token
     */
    private function peek(): Token
    {
        return $this->tokens[$this->current];
    }

    /**
     * Consume current token and return it
     *
     * @return \Qoliber\Tsuku\Lexer\Token
     */
    private function advance(): Token
    {
        $token = $this->tokens[$this->current];
        if ($token->type !== TokenType::EOF) {
            $this->current++;
        }

        return $token;
    }

    /**
     * Check if we're at end of tokens
     *
     * @return bool
     */
    private function isAtEnd(): bool
    {
        return $this->peek()->type === TokenType::EOF;
    }
}
