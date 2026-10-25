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
 * left OP right with OP one of == != > < >= <= in "not in"
 */
final class ComparisonExpression implements Expression
{
    public function __construct(
        public readonly string $operator,
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
        return ExpressionEvaluator::compare(
            $this->left->evaluate($evaluator, $data),
            $this->operator,
            $this->right->evaluate($evaluator, $data)
        );
    }
}
