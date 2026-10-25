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

enum TokenType: string
{
    case TEXT = 'TEXT';                         // Plain text content (also the "@" of an "@@" escape)
    case VARIABLE = 'VARIABLE';                 // {variable}
    case FUNCTION = 'FUNCTION';                 // @functionName(args), value "name|args"
    case TERNARY = 'TERNARY';                   // @?{condition "true" : "false"}
    case DIRECTIVE_FOR = 'DIRECTIVE_FOR';       // @for(...)
    case DIRECTIVE_IF = 'DIRECTIVE_IF';         // @if(...)
    case DIRECTIVE_UNLESS = 'DIRECTIVE_UNLESS'; // @unless(...)
    case DIRECTIVE_ELSE = 'DIRECTIVE_ELSE';     // @else
    case DIRECTIVE_ELSEIF = 'DIRECTIVE_ELSEIF'; // @elseif(...)
    case DIRECTIVE_MATCH = 'DIRECTIVE_MATCH';   // @match(...)
    case DIRECTIVE_CASE = 'DIRECTIVE_CASE';     // @case(...)
    case DIRECTIVE_DEFAULT = 'DIRECTIVE_DEFAULT'; // @default
    case DIRECTIVE_CUSTOM = 'DIRECTIVE_CUSTOM'; // registered block directive @name(args) / @name, value "name|args"
    case DIRECTIVE_END = 'DIRECTIVE_END';       // @end
    case EOF = 'EOF';                           // End of file

    /**
     * Block directives open, split or close a block and take part in whitespace control.
     *
     * @return bool
     */
    public function isBlockDirective(): bool
    {
        return match ($this) {
            self::TEXT, self::VARIABLE, self::FUNCTION, self::TERNARY, self::EOF => false,
            default => true,
        };
    }

    /**
     * Directives that open a block closed by @end.
     *
     * @return bool
     */
    public function opensBlock(): bool
    {
        return match ($this) {
            self::DIRECTIVE_FOR, self::DIRECTIVE_IF, self::DIRECTIVE_UNLESS,
            self::DIRECTIVE_MATCH, self::DIRECTIVE_CUSTOM => true,
            default => false,
        };
    }

    /**
     * Directives that start the next branch of the enclosing block.
     *
     * @return bool
     */
    public function isBranch(): bool
    {
        return match ($this) {
            self::DIRECTIVE_ELSE, self::DIRECTIVE_ELSEIF, self::DIRECTIVE_CASE, self::DIRECTIVE_DEFAULT => true,
            default => false,
        };
    }
}
