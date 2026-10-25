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

use Qoliber\Tsuku\Exception\FunctionNotFoundException;
use Qoliber\Tsuku\Function\FunctionRegistry;

class ExpressionEvaluator
{
    /** @var array<string, \Qoliber\Tsuku\Expression\Expression> */
    private array $parsed = [];

    private ?ExpressionParser $parser = null;

    public function __construct(
        private readonly ?FunctionRegistry $functionRegistry = null,
    ) {
    }

    /**
     * Evaluate a condition string with data context
     *
     * @param string $expression
     * @param array<mixed> $data
     * @return bool
     * @throws \Qoliber\Tsuku\Exception\ParseException When the condition is malformed
     * @throws \Qoliber\Tsuku\Exception\FunctionNotFoundException When it calls an unknown function
     */
    public function evaluate(string $expression, array $data): bool
    {
        return $this->evaluateExpression($this->parse($expression), $data);
    }

    /**
     * Evaluate an already parsed condition
     *
     * @param \Qoliber\Tsuku\Expression\Expression $expression
     * @param array<mixed> $data
     * @return bool
     */
    public function evaluateExpression(Expression $expression, array $data): bool
    {
        return self::isTruthy($expression->evaluate($this, $data));
    }

    /**
     * Parse a condition string (cached per evaluator)
     *
     * @param string $expression
     * @return \Qoliber\Tsuku\Expression\Expression
     * @throws \Qoliber\Tsuku\Exception\ParseException
     */
    public function parse(string $expression): Expression
    {
        $this->parser ??= new ExpressionParser($this->functionRegistry);

        return $this->parsed[$expression] ??= $this->parser->parseCondition($expression);
    }

    /**
     * Call a registered function from inside a condition
     *
     * @param string $name
     * @param array<int, mixed> $arguments
     * @return mixed
     * @throws \Qoliber\Tsuku\Exception\FunctionNotFoundException
     */
    public function callFunction(string $name, array $arguments): mixed
    {
        if ($this->functionRegistry === null || !$this->functionRegistry->has($name)) {
            throw FunctionNotFoundException::named($name, "Unknown function '@{$name}' in condition");
        }

        return $this->functionRegistry->execute($name, $arguments);
    }

    /**
     * Truthiness used by conditions: everything PHP's empty() does not reject
     *
     * @param mixed $value
     * @return bool
     */
    public static function isTruthy(mixed $value): bool
    {
        return !empty($value);
    }

    /**
     * Compare two values (loose PHP comparison as in 1.3.0, plus in / not in)
     *
     * @param mixed $left
     * @param string $operator
     * @param mixed $right
     * @return bool
     */
    public static function compare(mixed $left, string $operator, mixed $right): bool
    {
        return match ($operator) {
            '>' => $left > $right,
            '<' => $left < $right,
            '>=' => $left >= $right,
            '<=' => $left <= $right,
            '==' => $left == $right,
            '!=' => $left != $right,
            'in' => Membership::contains($right, $left),
            'not in' => !Membership::contains($right, $left),
            default => throw new \InvalidArgumentException("Unsupported operator '{$operator}'"),
        };
    }
}
