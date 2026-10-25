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
 * String or number literal.
 */
final class LiteralExpression implements Expression
{
    public function __construct(
        public readonly string|int|float $value,
    ) {
    }

    /**
     * @param \Qoliber\Tsuku\Expression\ExpressionEvaluator $evaluator
     * @param array<mixed> $data
     * @return mixed
     */
    public function evaluate(ExpressionEvaluator $evaluator, array $data): mixed
    {
        return $this->value;
    }
}
