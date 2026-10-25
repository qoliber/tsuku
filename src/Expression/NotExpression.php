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
 * not x / !x
 */
final class NotExpression implements Expression
{
    public function __construct(
        public readonly Expression $operand,
    ) {
    }

    /**
     * @param \Qoliber\Tsuku\Expression\ExpressionEvaluator $evaluator
     * @param array<mixed> $data
     * @return bool
     */
    public function evaluate(ExpressionEvaluator $evaluator, array $data): bool
    {
        return !ExpressionEvaluator::isTruthy($this->operand->evaluate($evaluator, $data));
    }
}
