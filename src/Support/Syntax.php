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

namespace Qoliber\Tsuku\Support;

/**
 * Quote-aware scanning shared by the lexer, the parser and the compiler.
 *
 * A quote (" or ') starts a string literal unless it is escaped with a backslash
 * or directly follows a letter, digit or underscore (an apostrophe as in Bob's).
 * A string ends at the next unescaped quote of the same kind. Parentheses, commas
 * and braces inside string literals are ignored.
 *
 * @internal
 */
final class Syntax
{
    /**
     * Find the parenthesis that closes the one at $openOffset.
     *
     * Parentheses inside string literals do not count. If the quote-aware scan
     * cannot find a match (for example an unterminated quote), the plain 1.3.0
     * scan that ignores quotes is used, so text 1.3.0 accepted still lexes.
     *
     * @param string $text
     * @param int $openOffset Offset of the opening parenthesis
     * @return int|null Offset of the closing parenthesis, or null when unbalanced
     */
    public static function findClosingParenthesis(string $text, int $openOffset): ?int
    {
        return self::scanForClose($text, $openOffset, true) ?? self::scanForClose($text, $openOffset, false);
    }

    /**
     * Find the first "}" outside string literals at or after $offset.
     *
     * @param string $text
     * @param int $offset
     * @return int|null
     */
    public static function findClosingBrace(string $text, int $offset): ?int
    {
        $length = strlen($text);
        $quote = null;

        for ($i = $offset; $i < $length; $i++) {
            $char = $text[$i];
            if ($quote !== null) {
                if ($char === $quote && $text[$i - 1] !== '\\') {
                    $quote = null;
                }
                continue;
            }
            if ($char === '}') {
                return $i;
            }
            if (self::opensString($text, $i)) {
                $quote = $char;
            }
        }

        return null;
    }

    /**
     * Split a comma-separated argument list, respecting quotes and nested parentheses.
     *
     * Arguments are trimmed; a trailing empty argument is dropped (as in 1.3.0).
     *
     * @param string $arguments
     * @return array<int, string>
     */
    public static function splitArguments(string $arguments): array
    {
        $result = [];
        $current = '';
        $quote = null;
        $depth = 0;
        $length = strlen($arguments);

        for ($i = 0; $i < $length; $i++) {
            $char = $arguments[$i];

            if ($quote !== null) {
                if ($char === $quote && $arguments[$i - 1] !== '\\') {
                    $quote = null;
                }
                $current .= $char;
                continue;
            }

            if (self::opensString($arguments, $i)) {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $result[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $result[] = trim($current);
        }

        return $result;
    }

    /**
     * Whether the character at $offset opens a string literal.
     *
     * @param string $text
     * @param int $offset
     * @return bool
     */
    public static function opensString(string $text, int $offset): bool
    {
        $char = $text[$offset];
        if ($char !== '"' && $char !== "'") {
            return false;
        }
        if ($offset === 0) {
            return true;
        }

        $previous = $text[$offset - 1];

        return $previous !== '\\' && !self::isWordCharacter($previous);
    }

    /**
     * @param string $char
     * @return bool
     */
    private static function isWordCharacter(string $char): bool
    {
        $code = ord($char);

        return $code === 95
            || ($code >= 48 && $code <= 57)
            || ($code >= 65 && $code <= 90)
            || ($code >= 97 && $code <= 122);
    }

    /**
     * @param string $text
     * @param int $openOffset
     * @param bool $quoteAware
     * @return int|null
     */
    private static function scanForClose(string $text, int $openOffset, bool $quoteAware): ?int
    {
        $length = strlen($text);
        $depth = 0;
        $quote = null;

        for ($i = $openOffset; $i < $length; $i++) {
            $char = $text[$i];

            if ($quote !== null) {
                if ($char === $quote && $text[$i - 1] !== '\\') {
                    $quote = null;
                }
                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            } elseif ($quoteAware && self::opensString($text, $i)) {
                $quote = $char;
            }
        }

        return null;
    }
}
