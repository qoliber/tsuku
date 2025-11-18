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

use Qoliber\Tsuku\Lexer\Token;
use Qoliber\Tsuku\Lexer\TokenType;
use Qoliber\Tsuku\Exception\ParseException;

class Parser
{
    private int $current = 0;

    /**
     * @param array<\Qoliber\Tsuku\Lexer\Token> $tokens
     */
    public function __construct(
        private readonly array $tokens,
    ) {
    }

    /**
     * Parse tokens into AST
     *
     * @return \Qoliber\Tsuku\Ast\TemplateNode
     */
    public function parse(): TemplateNode
    {
        $children = [];

        while (!$this->isAtEnd()) {
            $node = $this->parseNode();
            if ($node !== null) {
                $children[] = $node;
            }
        }

        return new TemplateNode($children);
    }

    /**
     * Parse a single node
     *
     * @return \Qoliber\Tsuku\Ast\Node|null
     */
    private function parseNode(): ?Node
    {
        $token = $this->peek();

        return match ($token->type) {
            TokenType::TEXT => $this->parseText(),
            TokenType::VARIABLE => $this->parseVariable(),
            TokenType::TERNARY => $this->parseTernary(),
            TokenType::FUNCTION => $this->parseFunction(),
            TokenType::DIRECTIVE_FOR => $this->parseFor(),
            TokenType::DIRECTIVE_IF => $this->parseIf(false),
            TokenType::DIRECTIVE_UNLESS => $this->parseIf(true),
            TokenType::DIRECTIVE_MATCH => $this->parseMatch(),
            TokenType::DIRECTIVE_ELSE => throw new ParseException(
                "Unexpected @else at line {$token->line} - @else must follow @if or @unless"
            ),
            TokenType::DIRECTIVE_CASE => throw new ParseException(
                "Unexpected @case at line {$token->line} - @case must be inside @match"
            ),
            TokenType::DIRECTIVE_DEFAULT => throw new ParseException(
                "Unexpected @default at line {$token->line} - @default must be inside @match"
            ),
            TokenType::DIRECTIVE_END => throw new ParseException(
                "Unexpected @end at line {$token->line}"
            ),
            TokenType::EOF => null,
        };
    }

    /**
     * Parse text node
     *
     * @return \Qoliber\Tsuku\Ast\TextNode
     */
    private function parseText(): TextNode
    {
        $token = $this->advance();
        return new TextNode($token->value);
    }

    /**
     * Parse variable node
     *
     * @return \Qoliber\Tsuku\Ast\VariableNode
     */
    private function parseVariable(): VariableNode
    {
        $token = $this->advance();
        return new VariableNode($token->value);
    }

    /**
     * Parse ternary expression node
     *
     * New format: @?{condition "true" : "false"}
     * Supports both old syntax with ? and new syntax without
     *
     * @return \Qoliber\Tsuku\Ast\TernaryNode
     */
    private function parseTernary(): TernaryNode
    {
        $token = $this->advance();
        $content = $token->value;

        // Parse: "condition ? trueValue : falseValue" OR "condition trueValue : falseValue"
        // The @? is already consumed by lexer, so we just need to find the :
        if (!preg_match('/^(.+?)\s*:\s*(.+?)$/s', $content, $matches)) {
            throw new ParseException(
                "Invalid ternary syntax at line {$token->line}: '{$content}'. Expected: 'condition \"true\" : \"false\"'"
            );
        }

        $leftPart = trim($matches[1]);
        $falseValue = trim($matches[2]);

        // Check if left part contains ? (old syntax)
        if (preg_match('/^(.+?)\s*\?\s*(.+?)$/s', $leftPart, $condMatches)) {
            $condition = trim($condMatches[1]);
            $trueValue = trim($condMatches[2]);
        } else {
            // New syntax: condition followed by value (variable, quoted string, number, or @function(...))
            // Find the last value: quoted string, @function(...), variable, or number as the true value
            if (preg_match('/^(.+?)\s+(@[a-zA-Z_][a-zA-Z0-9_]*\(.+?\)|["' . "'" . '][^"' . "'" . ']*["' . "'" . ']|[a-zA-Z_][a-zA-Z0-9_\.]*|[0-9]+\.?[0-9]*)$/su', $leftPart, $parts)) {
                $condition = trim($parts[1]);
                $trueValue = trim($parts[2]);
            } else {
                throw new ParseException(
                    "Invalid ternary syntax at line {$token->line}: '{$content}'. Expected: 'condition \"true\" : \"false\"'"
                );
            }
        }

        return new TernaryNode($condition, $trueValue, $falseValue);
    }

    /**
     * Parse function call node
     *
     * New format: @functionName{args}
     * Token value format: "functionName|args"
     *
     * @return \Qoliber\Tsuku\Ast\FunctionNode
     */
    private function parseFunction(): FunctionNode
    {
        $token = $this->advance();
        $content = $token->value;

        // Parse: "functionName|arg1, arg2, ..."
        $parts = explode('|', $content, 2);
        if (count($parts) !== 2) {
            throw new ParseException(
                "Invalid function token format at line {$token->line}: '{$content}'"
            );
        }

        $functionName = $parts[0];
        $argsString = $parts[1];

        // Parse arguments - comma separated, respecting quotes
        $arguments = [];
        if (trim($argsString) !== '') {
            $arguments = $this->parseArguments($argsString);
        }

        return new FunctionNode($functionName, $arguments);
    }

    /**
     * Parse space-separated arguments, respecting quoted strings
     *
     * @param string $argsString
     * @return array<string>
     */
    private function parseSpaceSeparatedArguments(string $argsString): array
    {
        $arguments = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        $length = strlen($argsString);

        for ($i = 0; $i < $length; $i++) {
            $char = $argsString[$i];

            if (($char === '"' || $char === "'") && ($i === 0 || $argsString[$i - 1] !== '\\')) {
                if (!$inQuotes) {
                    $inQuotes = true;
                    $quoteChar = $char;
                } elseif ($char === $quoteChar) {
                    $inQuotes = false;
                    $quoteChar = null;
                }
                $current .= $char;
            } elseif ($char === ' ' && !$inQuotes) {
                if ($current !== '') {
                    $arguments[] = trim($current);
                    $current = '';
                }
            } else {
                $current .= $char;
            }
        }

        if ($current !== '') {
            $arguments[] = trim($current);
        }

        return $arguments;
    }

    /**
     * Parse comma-separated arguments, respecting quoted strings (for ternary)
     *
     * @param string $argsString
     * @return array<string>
     */
    private function parseArguments(string $argsString): array
    {
        $arguments = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        $parenDepth = 0;
        $length = strlen($argsString);

        for ($i = 0; $i < $length; $i++) {
            $char = $argsString[$i];

            if (($char === '"' || $char === "'") && ($i === 0 || $argsString[$i - 1] !== '\\')) {
                if (!$inQuotes) {
                    $inQuotes = true;
                    $quoteChar = $char;
                } elseif ($char === $quoteChar) {
                    $inQuotes = false;
                    $quoteChar = null;
                }
                $current .= $char;
            } elseif ($char === '(' && !$inQuotes) {
                $parenDepth++;
                $current .= $char;
            } elseif ($char === ')' && !$inQuotes) {
                $parenDepth--;
                $current .= $char;
            } elseif ($char === ',' && !$inQuotes && $parenDepth === 0) {
                $arguments[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if ($current !== '') {
            $arguments[] = trim($current);
        }

        return $arguments;
    }

    /**
     * Parse for loop node
     *
     * @return \Qoliber\Tsuku\Ast\ForNode
     */
    private function parseFor(): ForNode
    {
        $token = $this->advance();
        $args = $token->value;

        // Parse: "items as item" or "items as key, value"
        if (!preg_match('/^\s*(\w+(?:\.\w+)*)\s+as\s+(\w+)(?:\s*,\s*(\w+))?\s*$/i', $args, $matches)) {
            throw new ParseException(
                "Invalid @tsuku:for syntax at line {$token->line}: '{$args}'. Expected: 'items as item' or 'items as key, value'"
            );
        }

        $collection = $matches[1];
        $itemVar = $matches[2];
        $keyVar = $matches[3] ?? null;

        // Parse children until @tsuku:end
        $children = $this->parseUntilEnd();

        return new ForNode($collection, $itemVar, $keyVar, $children);
    }

    /**
     * Parse if/unless node
     *
     * @param bool $negate Whether this is an unless
     * @return \Qoliber\Tsuku\Ast\IfNode
     */
    private function parseIf(bool $negate): IfNode
    {
        $token = $this->advance();
        $condition = trim($token->value);

        // Parse children until @else or @end
        $children = $this->parseUntilElseOrEnd();

        // Check if we have an @else
        $elseChildren = [];
        if ($this->peek()->type === TokenType::DIRECTIVE_ELSE) {
            $this->advance(); // Consume @else
            // Parse else children until @end
            $elseChildren = $this->parseUntilEnd();
        } elseif ($this->peek()->type === TokenType::DIRECTIVE_END) {
            $this->advance(); // Consume @end
        } else {
            throw new ParseException("Expected @else or @end but reached end of file");
        }

        return new IfNode($condition, $negate, $children, $elseChildren);
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
        $expression = trim($token->value);

        $cases = [];
        $defaultChildren = [];

        // Parse cases and default until @end
        while (!$this->isAtEnd() && $this->peek()->type !== TokenType::DIRECTIVE_END) {
            $currentToken = $this->peek();

            if ($currentToken->type === TokenType::DIRECTIVE_CASE) {
                $cases[] = $this->parseCase();
            } elseif ($currentToken->type === TokenType::DIRECTIVE_DEFAULT) {
                $this->advance(); // Consume @default
                $defaultChildren = $this->parseUntilCaseDefaultOrEnd();
                break; // @default should be last
            } else {
                throw new ParseException(
                    "Expected @case or @default inside @match at line {$currentToken->line}"
                );
            }
        }

        // Consume @end
        if ($this->peek()->type === TokenType::DIRECTIVE_END) {
            $this->advance();
        } else {
            throw new ParseException("Expected @end for @match but reached end of file");
        }

        return new MatchNode($expression, $cases, $defaultChildren);
    }

    /**
     * Parse @case directive
     *
     * @return \Qoliber\Tsuku\Ast\CaseNode
     * @throws \Qoliber\Tsuku\Exception\ParseException
     */
    private function parseCase(): CaseNode
    {
        $token = $this->advance();
        $valuesString = trim($token->value);

        // Parse comma-separated values, respecting quotes
        $values = $this->parseArguments($valuesString);

        // Parse children until next @case, @default, or @end
        $children = $this->parseUntilCaseDefaultOrEnd();

        return new CaseNode($values, $children);
    }

    /**
     * Parse nodes until we hit @case, @default, or @end
     *
     * @return array<\Qoliber\Tsuku\Ast\Node>
     */
    private function parseUntilCaseDefaultOrEnd(): array
    {
        $children = [];

        while (!$this->isAtEnd()
            && $this->peek()->type !== TokenType::DIRECTIVE_END
            && $this->peek()->type !== TokenType::DIRECTIVE_CASE
            && $this->peek()->type !== TokenType::DIRECTIVE_DEFAULT) {
            $node = $this->parseNode();
            if ($node !== null) {
                $children[] = $node;
            }
        }

        return $children;
    }

    /**
     * Parse nodes until we hit @else or @end
     *
     * @return array<\Qoliber\Tsuku\Ast\Node>
     */
    private function parseUntilElseOrEnd(): array
    {
        $children = [];

        while (!$this->isAtEnd()
            && $this->peek()->type !== TokenType::DIRECTIVE_END
            && $this->peek()->type !== TokenType::DIRECTIVE_ELSE) {
            $node = $this->parseNode();
            if ($node !== null) {
                $children[] = $node;
            }
        }

        return $children;
    }

    /**
     * Parse nodes until we hit @end
     *
     * @return array<\Qoliber\Tsuku\Ast\Node>
     */
    private function parseUntilEnd(): array
    {
        $children = [];

        while (!$this->isAtEnd() && $this->peek()->type !== TokenType::DIRECTIVE_END) {
            $node = $this->parseNode();
            if ($node !== null) {
                $children[] = $node;
            }
        }

        // Consume the @end
        if ($this->peek()->type === TokenType::DIRECTIVE_END) {
            $this->advance();
        } else {
            throw new ParseException("Expected @end but reached end of file");
        }

        return $children;
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
        if (!$this->isAtEnd()) {
            $this->current++;
        }
        return $this->tokens[$this->current - 1];
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
