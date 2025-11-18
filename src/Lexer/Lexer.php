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

namespace Qoliber\Tsuku\Lexer;

use Qoliber\Tsuku\Exception\ParseException;

class Lexer
{
    private int $position = 0;
    private int $line = 1;
    private int $column = 1;
    private int $length;
    private readonly string $input;

    public function __construct(
        string $input,
    ) {
        // Preprocess: handle line continuation (backslash before newline)
        $this->input = $this->preprocessLineContinuation($input);
        $this->length = strlen($this->input);
    }

    /**
     * Preprocess input to handle line continuation (backslash + newline)
     *
     * @param string $input The raw template input
     * @return string Processed input with line continuations removed
     */
    private function preprocessLineContinuation(string $input): string
    {
        // Replace backslash followed by newline (\n or \r\n) with nothing
        return preg_replace('/\\\\(?:\r\n|\n|\r)/', '', $input);
    }

    /**
     * Tokenize the entire input
     *
     * @return array<\Qoliber\Tsuku\Lexer\Token>
     */
    public function tokenize(): array
    {
        $tokens = [];

        while (!$this->isAtEnd()) {
            $token = $this->nextToken();
            if ($token !== null) {
                $tokens[] = $token;
            }
        }

        $tokens[] = new Token(TokenType::EOF, '', $this->line, $this->column);

        return $tokens;
    }

    /**
     * Get the next token
     *
     * @return \Qoliber\Tsuku\Lexer\Token|null
     */
    private function nextToken(): ?Token
    {
        // Check for @ directives/functions/ternary first
        if ($this->peek() === '@') {
            return $this->scanAtSymbol();
        }

        // Check for simple variables {name}
        if ($this->peek() === '{') {
            $varToken = $this->scanVariable();
            if ($varToken !== null) {
                return $varToken;
            }
        }

        // Everything else is text
        return $this->scanText();
    }

    /**
     * Scan @ symbol - could be directive, function, or ternary
     *
     * @return \Qoliber\Tsuku\Lexer\Token
     * @throws \Qoliber\Tsuku\Exception\ParseException
     */
    private function scanAtSymbol(): Token
    {
        $startLine = $this->line;
        $startColumn = $this->column;
        $remaining = substr($this->input, $this->position);

        // Try to match directives: @for(...), @if(...), @unless(...), @end
        if (preg_match('/^@for\s*\(/', $remaining, $matches)) {
            $startPos = strlen($matches[0]) - 1;
            $args = $this->extractBalancedParentheses($remaining, $startPos);
            if ($args !== null) {
                $fullMatch = '@for(' . $args . ')';
                $this->advance(strlen($fullMatch));
                $this->consumeTrailingNewline();
                return new Token(TokenType::DIRECTIVE_FOR, $args, $startLine, $startColumn);
            }
        }

        if (preg_match('/^@if\s*\(/', $remaining, $matches)) {
            $startPos = strlen($matches[0]) - 1;
            $args = $this->extractBalancedParentheses($remaining, $startPos);
            if ($args !== null) {
                $fullMatch = '@if(' . $args . ')';
                $this->advance(strlen($fullMatch));
                $this->consumeTrailingNewline();
                return new Token(TokenType::DIRECTIVE_IF, $args, $startLine, $startColumn);
            }
        }

        if (preg_match('/^@unless\s*\(/', $remaining, $matches)) {
            $startPos = strlen($matches[0]) - 1;
            $args = $this->extractBalancedParentheses($remaining, $startPos);
            if ($args !== null) {
                $fullMatch = '@unless(' . $args . ')';
                $this->advance(strlen($fullMatch));
                $this->consumeTrailingNewline();
                return new Token(TokenType::DIRECTIVE_UNLESS, $args, $startLine, $startColumn);
            }
        }

        if (preg_match('/^@match\s*\(/', $remaining, $matches)) {
            $startPos = strlen($matches[0]) - 1;
            $args = $this->extractBalancedParentheses($remaining, $startPos);
            if ($args !== null) {
                $fullMatch = '@match(' . $args . ')';
                $this->advance(strlen($fullMatch));
                $this->consumeTrailingNewline();
                return new Token(TokenType::DIRECTIVE_MATCH, $args, $startLine, $startColumn);
            }
        }

        if (preg_match('/^@case\s*\(/', $remaining, $matches)) {
            $startPos = strlen($matches[0]) - 1;
            $args = $this->extractBalancedParentheses($remaining, $startPos);
            if ($args !== null) {
                $fullMatch = '@case(' . $args . ')';
                $this->advance(strlen($fullMatch));
                $this->consumeTrailingNewline();
                return new Token(TokenType::DIRECTIVE_CASE, $args, $startLine, $startColumn);
            }
        }

        // Match @default directive (NOT followed by opening parenthesis)
        if (preg_match('/^@default(?!\s*\()/', $remaining)) {
            $this->advance(strlen('@default'));
            $this->consumeTrailingNewline();
            return new Token(TokenType::DIRECTIVE_DEFAULT, '', $startLine, $startColumn);
        }

        if (preg_match('/^@else/', $remaining)) {
            $this->advance(strlen('@else'));
            $this->consumeTrailingNewline();
            return new Token(TokenType::DIRECTIVE_ELSE, '', $startLine, $startColumn);
        }

        if (preg_match('/^@end/', $remaining)) {
            $this->advance(strlen('@end'));
            $this->consumeTrailingNewline();
            return new Token(TokenType::DIRECTIVE_END, '', $startLine, $startColumn);
        }

        // Try to match ternary: @?{condition "true" : "false"}
        if (preg_match('/^@\?\{(.+?)\}/s', $remaining, $matches)) {
            $fullMatch = $matches[0];
            $content = $matches[1];
            $this->advance(strlen($fullMatch));
            return new Token(TokenType::TERNARY, $content, $startLine, $startColumn);
        }

        // Try to match function: @functionName(args)
        if (preg_match('/^@([a-zA-Z_][a-zA-Z0-9_]*)\(/', $remaining, $matches)) {
            $functionName = $matches[1];
            $startPos = strlen($matches[0]);

            // Extract balanced parentheses content
            $args = $this->extractBalancedParentheses($remaining, $startPos - 1);
            if ($args !== null) {
                $fullMatch = '@' . $functionName . '(' . $args . ')';
                $this->advance(strlen($fullMatch));
                return new Token(TokenType::FUNCTION, $functionName . '|' . $args, $startLine, $startColumn);
            }
        }

        throw new ParseException("Invalid @ syntax at line {$this->line}, column {$this->column}");
    }

    /**
     * Extract content between balanced parentheses
     *
     * @param string $text The text to parse
     * @param int $startPos Position of the opening parenthesis
     * @return string|null The content between parentheses, or null if unbalanced
     */
    private function extractBalancedParentheses(string $text, int $startPos): ?string
    {
        $depth = 0;
        $length = strlen($text);
        $content = '';

        for ($i = $startPos; $i < $length; $i++) {
            $char = $text[$i];

            if ($char === '(') {
                $depth++;
                if ($depth > 1) {
                    $content .= $char;
                }
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $content;
                }
                $content .= $char;
            } else {
                if ($depth > 0) {
                    $content .= $char;
                }
            }
        }

        return null; // Unbalanced parentheses
    }

    /**
     * Consume a single trailing newline if present
     *
     * @return void
     */
    private function consumeTrailingNewline(): void
    {
        if ($this->peek() === "\n") {
            $this->advance();
        }
    }

    /**
     * Scan a simple variable: {name}
     *
     * @return \Qoliber\Tsuku\Lexer\Token|null
     */
    private function scanVariable(): ?Token
    {
        $startLine = $this->line;
        $startColumn = $this->column;
        $remaining = substr($this->input, $this->position);

        // Check for simple variable: {word} or {word.word}
        if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_\.]*)\}/', $remaining, $matches)) {
            $fullMatch = $matches[0];
            $varName = $matches[1];
            $this->advance(strlen($fullMatch));
            return new Token(TokenType::VARIABLE, $varName, $startLine, $startColumn);
        }

        // Not a variable, treat { as text
        return null;
    }

    /**
     * Scan text until we hit a directive, function, or variable
     *
     * @return \Qoliber\Tsuku\Lexer\Token|null
     */
    private function scanText(): ?Token
    {
        $startLine = $this->line;
        $startColumn = $this->column;
        $text = '';

        while (!$this->isAtEnd()) {
            $char = $this->peek();

            // Stop at @ symbol
            if ($char === '@') {
                break;
            }

            // Check if this is a variable
            if ($char === '{') {
                // Lookahead to see if it matches variable pattern
                $remaining = substr($this->input, $this->position);
                if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_\.]*)\}/', $remaining)) {
                    // It's a variable, stop here
                    break;
                }
                // Not recognized, consume { as text
            }

            $text .= $char;
            $this->advance();
        }

        if ($text === '') {
            return null;
        }

        return new Token(TokenType::TEXT, $text, $startLine, $startColumn);
    }

    /**
     * Check if we're at the end of input
     *
     * @return bool
     */
    private function isAtEnd(): bool
    {
        return $this->position >= $this->length;
    }

    /**
     * Get current character without consuming
     *
     * @return string
     */
    private function peek(): string
    {
        if ($this->isAtEnd()) {
            return '';
        }
        return $this->input[$this->position];
    }

    /**
     * Consume current character and move forward
     *
     * @param int $count Number of characters to advance
     * @return void
     */
    private function advance(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            if ($this->isAtEnd()) {
                break;
            }

            if ($this->input[$this->position] === "\n") {
                $this->line++;
                $this->column = 1;
            } else {
                $this->column++;
            }

            $this->position++;
        }
    }
}
