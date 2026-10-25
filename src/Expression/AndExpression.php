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
 * a and b / a && b (short-circuit)
 */
final class AndExpression implements Expression
{
    public function __construct(
        public readonly Expression $left,
        public readonly Expression $right,
    ) {
    }

    /**
     * @param \Qoliber\Tsuku\Expression\ExpressionEvaluator $evaluator
     * @param array<mixed> $data
     * @return bool
     */
    public function evaluate(ExpressionEvaluator $evaluator, array $data): bool
    {
        return ExpressionEvaluator::isTruthy($this->left->evaluate($evaluator, $data))
            && ExpressionEvaluator::isTruthy($this->right->evaluate($evaluator, $data));
    }
}
