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
 * Function call @name(arg, ...) inside a condition.
 */
final class FunctionCallExpression implements Expression
{
    /**
     * @param string $name Function name without "@"
     * @param array<int, \Qoliber\Tsuku\Expression\Expression> $arguments
     */
    public function __construct(
        public readonly string $name,
        public readonly array $arguments,
    ) {
    }

    /**
     * @param \Qoliber\Tsuku\Expression\ExpressionEvaluator $evaluator
     * @param array<mixed> $data
     * @return mixed
     */
    public function evaluate(ExpressionEvaluator $evaluator, array $data): mixed
    {
        $arguments = [];
        foreach ($this->arguments as $argument) {
            $arguments[] = $argument->evaluate($evaluator, $data);
        }

        return $evaluator->callFunction($this->name, $arguments);
    }
}
