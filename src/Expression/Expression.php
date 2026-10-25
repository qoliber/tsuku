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
 * Node of a parsed condition (see ExpressionParser).
 */
interface Expression
{
    /**
     * Evaluate the node; logical and comparison nodes return bool.
     *
     * @param \Qoliber\Tsuku\Expression\ExpressionEvaluator $evaluator
     * @param array<mixed> $data
     * @return mixed
     */
    public function evaluate(ExpressionEvaluator $evaluator, array $data): mixed;
}
