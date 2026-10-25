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
use Qoliber\Tsuku\Support\Syntax;

class Lexer
{
    /** Built-in directives that take a parenthesised argument */
    private const DIRECTIVES_WITH_ARGUMENTS = [
        'for' => TokenType::DIRECTIVE_FOR,
        'if' => TokenType::DIRECTIVE_IF,
        'unless' => TokenType::DIRECTIVE_UNLESS,
        'match' => TokenType::DIRECTIVE_MATCH,
        'case' => TokenType::DIRECTIVE_CASE,
        'elseif' => TokenType::DIRECTIVE_ELSEIF,
    ];

    private const VARIABLE_PATTERN = '/\G\{([a-zA-Z_][a-zA-Z0-9_\.]*)\}/';

    private int $position = 0;
    private readonly int $length;
    private readonly string $input;
    private readonly SourceMap $sourceMap;

    /** @var array<string, true> */
    private array $blockDirectives = [];

    /**
     * @param string $input Template text
     * @param array<int, string> $blockDirectives Names of registered custom block directives
     */
    public function __construct(
        string $input,
        array $blockDirectives = [],
    ) {
        [$this->input, $continuations] = self::preprocessLineContinuation($input);
        $this->length = strlen($this->input);
        $this->sourceMap = new SourceMap($this->input, $continuations);
        foreach ($blockDirectives as $name) {
            $this->blockDirectives[$name] = true;
        }
    }

    /**
     * Tokenize the entire input, with whitespace control applied.
     *
     * @return array<\Qoliber\Tsuku\Lexer\Token>
     * @throws \Qoliber\Tsuku\Exception\ParseException
     */
    public function tokenize(): array
    {
        $tokens = (new WhitespaceControl())->apply($this->tokenizeRaw());
        [$line, $column] = $this->sourceMap->position($this->length);
        $tokens[] = new Token(TokenType::EOF, '', $line, $column, $this->length);

        return $tokens;
    }

    /**
     * Tokens exactly as written: no whitespace control and no EOF token.
     *
     * @return array<int, \Qoliber\Tsuku\Lexer\Token>
     * @throws \Qoliber\Tsuku\Exception\ParseException
     */
    public function tokenizeRaw(): array
    {
        $this->position = 0;
        $tokens = [];
        $last = -1;

        while ($this->position < $this->length) {
            $token = $this->nextToken();

            if ($token->type === TokenType::TEXT && $last >= 0 && $tokens[$last]->type === TokenType::TEXT) {
                $previous = $tokens[$last];
                $tokens[$last] = new Token(
                    TokenType::TEXT,
                    $previous->value . $token->value,
                    $previous->line,
                    $previous->column,
                    $previous->valueOffset
                );
                continue;
            }

            $tokens[] = $token;
            $last++;
        }

        return $tokens;
    }

    /**
     * Line/column lookup for offsets in the preprocessed template.
     *
     * @return \Qoliber\Tsuku\Lexer\SourceMap
     */
    public function getSourceMap(): SourceMap
    {
        return $this->sourceMap;
    }

    /**
     * Remove "\" + line break (line continuation) and remember where it happened.
     *
     * @param string $input The raw template input
     * @return array{0: string, 1: array<int, int>} Processed text and continuation offsets
     */
    private static function preprocessLineContinuation(string $input): array
    {
        if (!str_contains($input, '\\')) {
            return [$input, []];
        }

        /** @var array<int, string> $parts */
        $parts = preg_split('/\\\\(\r\n|\n|\r)/', $input, -1, PREG_SPLIT_DELIM_CAPTURE);

        $text = '';
        $continuations = [];
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $text .= $part;
            } elseif ($part !== "\r") {
                $continuations[] = strlen($text);
            }
        }

        return [$text, $continuations];
    }

    /**
     * @return \Qoliber\Tsuku\Lexer\Token
     * @throws \Qoliber\Tsuku\Exception\ParseException
     */
    private function nextToken(): Token
    {
        $char = $this->input[$this->position];

        if ($char === '@') {
            return $this->scanAtSymbol();
        }

        if ($char === '{' && preg_match(self::VARIABLE_PATTERN, $this->input, $matches, 0, $this->position) === 1) {
            $start = $this->position;
            $this->position += strlen($matches[0]);
            return $this->token(TokenType::VARIABLE, $matches[1], $start, $start + 1);
        }

        return $this->scanText();
    }

    /**
     * Scan @ symbol: escape, inline condition, directive or function
     *
     * @return \Qoliber\Tsuku\Lexer\Token
     * @throws \Qoliber\Tsuku\Exception\ParseException
     */
    private function scanAtSymbol(): Token
    {
        $start = $this->position;
        $next = $this->input[$start + 1] ?? '';

        // @@ prints a literal @
        if ($next === '@') {
            $this->position += 2;
            return $this->token(TokenType::TEXT, '@', $start, $start + 1);
        }

        // Inline condition: @?{condition "true" : "false"}
        if ($next === '?' && ($this->input[$start + 2] ?? '') === '{') {
            $close = Syntax::findClosingBrace($this->input, $start + 3);
            if ($close === null || $close === $start + 3) {
                throw $this->error('Invalid inline condition', $start);
            }
            $this->position = $close + 1;
            $content = substr($this->input, $start + 3, $close - $start - 3);

            return $this->token(TokenType::TERNARY, $content, $start, $start + 3);
        }

        if (preg_match('/\G@([a-zA-Z_][a-zA-Z0-9_]*)/', $this->input, $matches, 0, $start) !== 1) {
            throw $this->error('Invalid @ syntax', $start, ' (write @@ for a literal @)');
        }

        $name = $matches[1];
        $afterName = $start + 1 + strlen($name);

        if (isset($this->blockDirectives[$name])) {
            $open = $this->openingParenthesisAt($afterName, false);
            if ($open === null) {
                $this->position = $afterName;
                return $this->token(TokenType::DIRECTIVE_CUSTOM, $name . '|', $start, $afterName);
            }
            return $this->scanArguments(TokenType::DIRECTIVE_CUSTOM, $name, $start, $open, true);
        }

        if (isset(self::DIRECTIVES_WITH_ARGUMENTS[$name])) {
            $open = $this->openingParenthesisAt($afterName, true);
            if ($open !== null) {
                return $this->scanArguments(self::DIRECTIVES_WITH_ARGUMENTS[$name], $name, $start, $open, false);
            }
        }

        // 1.3.0 compatible prefix matching: "@default" not followed by "(", "@else...", "@end..."
        if (str_starts_with($name, 'default') && $this->openingParenthesisAt($start + 8, true) === null) {
            return $this->keyword(TokenType::DIRECTIVE_DEFAULT, $start, 8);
        }
        if (str_starts_with($name, 'else')) {
            return $this->keyword(TokenType::DIRECTIVE_ELSE, $start, 5);
        }
        if (str_starts_with($name, 'end')) {
            return $this->keyword(TokenType::DIRECTIVE_END, $start, 4);
        }

        $open = $this->openingParenthesisAt($afterName, false);
        if ($open !== null) {
            return $this->scanArguments(TokenType::FUNCTION, $name, $start, $open, true);
        }

        throw $this->error('Invalid @ syntax', $start, ' (write @@ for a literal @)');
    }

    /**
     * Read "(...)" starting at $open and produce the token.
     *
     * @param \Qoliber\Tsuku\Lexer\TokenType $type
     * @param string $name
     * @param int $start Offset of "@"
     * @param int $open Offset of "("
     * @param bool $prefixName Prefix the value with "name|" (functions, custom directives)
     * @return \Qoliber\Tsuku\Lexer\Token
     * @throws \Qoliber\Tsuku\Exception\ParseException
     */
    private function scanArguments(TokenType $type, string $name, int $start, int $open, bool $prefixName): Token
    {
        $close = Syntax::findClosingParenthesis($this->input, $open);
        if ($close === null) {
            throw $this->error("Unclosed parenthesis after @{$name}", $start);
        }

        $arguments = substr($this->input, $open + 1, $close - $open - 1);
        $this->position = $close + 1;

        return $this->token($type, $prefixName ? $name . '|' . $arguments : $arguments, $start, $open + 1);
    }

    /**
     * @param \Qoliber\Tsuku\Lexer\TokenType $type
     * @param int $start
     * @param int $length
     * @return \Qoliber\Tsuku\Lexer\Token
     */
    private function keyword(TokenType $type, int $start, int $length): Token
    {
        $this->position = $start + $length;

        return $this->token($type, '', $start, $start + $length);
    }

    /**
     * Offset of "(" at $offset (optionally after whitespace), or null.
     *
     * @param int $offset
     * @param bool $allowWhitespace
     * @return int|null
     */
    private function openingParenthesisAt(int $offset, bool $allowWhitespace): ?int
    {
        if ($allowWhitespace && $offset < $this->length) {
            $offset += strspn($this->input, " \t\r\n", $offset);
        }

        return ($this->input[$offset] ?? '') === '(' ? $offset : null;
    }

    /**
     * Scan text until the next "@" or variable
     *
     * @return \Qoliber\Tsuku\Lexer\Token
     */
    private function scanText(): Token
    {
        $start = $this->position;

        while ($this->position < $this->length) {
            $this->position += strcspn($this->input, '@{', $this->position);
            if ($this->position >= $this->length || $this->input[$this->position] === '@') {
                break;
            }
            if (preg_match(self::VARIABLE_PATTERN, $this->input, $matches, 0, $this->position) === 1) {
                break;
            }
            $this->position++;
        }

        return $this->token(TokenType::TEXT, substr($this->input, $start, $this->position - $start), $start, $start);
    }

    /**
     * @param \Qoliber\Tsuku\Lexer\TokenType $type
     * @param string $value
     * @param int $start
     * @param int $valueOffset
     * @return \Qoliber\Tsuku\Lexer\Token
     */
    private function token(TokenType $type, string $value, int $start, int $valueOffset): Token
    {
        [$line, $column] = $this->sourceMap->position($start);

        return new Token($type, $value, $line, $column, $valueOffset);
    }

    /**
     * @param string $message
     * @param int $offset
     * @param string $hint
     * @return \Qoliber\Tsuku\Exception\ParseException
     */
    private function error(string $message, int $offset, string $hint = ''): ParseException
    {
        [$line, $column] = $this->sourceMap->position($offset);

        return ParseException::at("{$message} at line {$line}, column {$column}{$hint}", $line, $column);
    }
}
