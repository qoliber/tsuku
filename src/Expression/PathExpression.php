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

use Qoliber\Tsuku\Support\DataAccessor;

/**
 * Variable path such as product.price (null when missing).
 */
final class PathExpression implements Expression
{
    public function __construct(
        public readonly string $path,
    ) {
    }

    /**
     * @param \Qoliber\Tsuku\Expression\ExpressionEvaluator $evaluator
     * @param array<mixed> $data
     * @return mixed
     */
    public function evaluate(ExpressionEvaluator $evaluator, array $data): mixed
    {
        return DataAccessor::get($data, $this->path);
    }
}
