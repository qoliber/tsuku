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

namespace Qoliber\Tsuku\Expression;

/**
 * Parsed @?{condition "true" : "false"}: the condition plus the source text of both values.
 */
final class InlineCondition
{
    public function __construct(
        public readonly Expression $expression,
        public readonly string $condition,
        public readonly string $trueValue,
        public readonly string $falseValue,
    ) {
    }
}
