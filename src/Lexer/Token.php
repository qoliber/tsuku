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

class Token
{
    /**
     * @param \Qoliber\Tsuku\Lexer\TokenType $type
     * @param string $value Token payload (text, variable path, directive arguments, ...)
     * @param int $line 1-based line of the token start in the template as written
     * @param int $column 1-based column of the token start
     * @param int $valueOffset Offset of $value in the preprocessed template, -1 when unknown
     */
    public function __construct(
        public readonly TokenType $type,
        public readonly string $value,
        public readonly int $line,
        public readonly int $column,
        public readonly int $valueOffset = -1,
    ) {
    }
}
