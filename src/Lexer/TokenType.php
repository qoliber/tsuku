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
    case TEXT = 'TEXT';                    // Plain text content
    case VARIABLE = 'VARIABLE';            // {variable}
    case FUNCTION = 'FUNCTION';            // @functionName{args}
    case TERNARY = 'TERNARY';              // @?{condition "true" : "false"}
    case DIRECTIVE_FOR = 'DIRECTIVE_FOR';  // @for(...)
    case DIRECTIVE_IF = 'DIRECTIVE_IF';    // @if(...)
    case DIRECTIVE_UNLESS = 'DIRECTIVE_UNLESS'; // @unless(...)
    case DIRECTIVE_ELSE = 'DIRECTIVE_ELSE';// @else
    case DIRECTIVE_MATCH = 'DIRECTIVE_MATCH'; // @match(...)
    case DIRECTIVE_CASE = 'DIRECTIVE_CASE';   // @case(...)
    case DIRECTIVE_DEFAULT = 'DIRECTIVE_DEFAULT'; // @default
    case DIRECTIVE_END = 'DIRECTIVE_END';  // @end
    case EOF = 'EOF';                      // End of file
}
