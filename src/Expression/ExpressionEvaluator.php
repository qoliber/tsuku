<?php

declare(strict_types=1);

/**
 * Tsuku - Transform data into any format
 *
 * @package   Qoliber\Tsuku
 * @author    Jakub Winkler <jwinkler@qoliber.com>
 * @copyright 2024 Qoliber
 * @license   MIT
 */

namespace Qoliber\Tsuku\Expression;

use Qoliber\Tsuku\Function\FunctionRegistry;

class ExpressionEvaluator
{
    public function __construct(
        private readonly ?FunctionRegistry $functionRegistry = null,
    ) {
    }

    /**
     * Evaluate expression with data context
     *
     * @param string $expression
     * @param array<mixed> $data
     * @return bool
     */
    public function evaluate(string $expression, array $data): bool
    {
        $expression = trim($expression);

        // Simple variable check (truthy)
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_\.]*$/', $expression)) {
            $value = $this->getNestedValue($data, $expression);
            return !empty($value);
        }

        // Comparison operators
        if (preg_match('/^(.+?)\s*(>=|<=|>|<|==|!=)\s*(.+)$/', $expression, $matches)) {
            $left = $this->resolveValue(trim($matches[1]), $data);
            $operator = $matches[2];
            $right = $this->resolveValue(trim($matches[3]), $data);

            return $this->compare($left, $operator, $right);
        }

        return false;
    }

    /**
     * Resolve value from expression part
     *
     * @param string $value
     * @param array<mixed> $data
     * @return mixed
     */
    private function resolveValue(string $value, array $data): mixed
    {
        // String literal
        if (preg_match('/^["' . "'" . '](.*)["' . "'" . ']$/', $value, $matches)) {
            return $matches[1];
        }

        // Number
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        // Function call: @functionName(args)
        if ($this->functionRegistry !== null && preg_match('/^@([a-zA-Z_][a-zA-Z0-9_]*)\(/', $value, $matches)) {
            $functionName = $matches[1];
            $argsStart = strlen($matches[0]) - 1;
            $args = $this->extractBalancedParentheses($value, $argsStart);

            if ($args !== null && $this->functionRegistry->has($functionName)) {
                // Parse arguments
                $arguments = [];
                if (trim($args) !== '') {
                    $arguments = $this->parseArguments($args);
                }

                // Resolve arguments recursively
                $resolvedArgs = array_map(
                    fn($arg) => $this->resolveValue(trim($arg), $data),
                    $arguments
                );

                // Execute function
                return $this->functionRegistry->execute($functionName, $resolvedArgs);
            }
        }

        // Variable
        return $this->getNestedValue($data, $value);
    }

    /**
     * Compare two values with operator
     *
     * @param mixed $left
     * @param string $operator
     * @param mixed $right
     * @return bool
     */
    private function compare(mixed $left, string $operator, mixed $right): bool
    {
        return match ($operator) {
            '>' => $left > $right,
            '<' => $left < $right,
            '>=' => $left >= $right,
            '<=' => $left <= $right,
            '==' => $left == $right,
            '!=' => $left != $right,
            default => false,
        };
    }

    /**
     * Extract content between balanced parentheses
     *
     * @param string $text
     * @param int $startPos
     * @return string|null
     */
    private function extractBalancedParentheses(string $text, int $startPos): ?string
    {
        $depth = 0;
        $length = strlen($text);
        $content = '';

        for ($i = $startPos; $i < $length; $i++) {
            $char = $text[$i];

            if ($char === '(') {
                $depth++;
                if ($depth > 1) {
                    $content .= $char;
                }
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $content;
                }
                $content .= $char;
            } else {
                if ($depth > 0) {
                    $content .= $char;
                }
            }
        }

        return null;
    }

    /**
     * Parse comma-separated arguments, respecting quotes and parentheses
     *
     * @param string $argsString
     * @return array<string>
     */
    private function parseArguments(string $argsString): array
    {
        $arguments = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        $parenDepth = 0;
        $length = strlen($argsString);

        for ($i = 0; $i < $length; $i++) {
            $char = $argsString[$i];

            if (($char === '"' || $char === "'") && ($i === 0 || $argsString[$i - 1] !== '\\')) {
                if (!$inQuotes) {
                    $inQuotes = true;
                    $quoteChar = $char;
                } elseif ($char === $quoteChar) {
                    $inQuotes = false;
                    $quoteChar = null;
                }
                $current .= $char;
            } elseif ($char === '(' && !$inQuotes) {
                $parenDepth++;
                $current .= $char;
            } elseif ($char === ')' && !$inQuotes) {
                $parenDepth--;
                $current .= $char;
            } elseif ($char === ',' && !$inQuotes && $parenDepth === 0) {
                $arguments[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if ($current !== '') {
            $arguments[] = trim($current);
        }

        return $arguments;
    }

    /**
     * Get nested value from array/object using dot notation
     * Supports: arrays, object properties, and method calls
     *
     * @param array<mixed> $data
     * @param string $key
     * @return mixed
     */
    private function getNestedValue(array $data, string $key): mixed
    {
        if (isset($data[$key])) {
            return $data[$key];
        }

        $keys = explode('.', $key);
        $value = $data;

        foreach ($keys as $k) {
            $value = $this->getValue($value, $k);
            if ($value === null) {
                return null;
            }
        }

        return $value;
    }

    /**
     * Get value from array or object - tries multiple strategies
     *
     * @param mixed $value Current value (array or object)
     * @param string $key Key to access
     * @return mixed
     */
    private function getValue(mixed $value, string $key): mixed
    {
        // Strategy 1: Array access
        if (is_array($value) && isset($value[$key])) {
            return $value[$key];
        }

        // Strategy 2: Object getter method (getName for "name")
        // Try getters first before direct property access
        if (is_object($value)) {
            $getter = 'get' . ucfirst($key);
            if (method_exists($value, $getter)) {
                return $value->$getter();
            }
        }

        // Strategy 3: Object is method (isActive for "active")
        if (is_object($value)) {
            $isMethod = 'is' . ucfirst($key);
            if (method_exists($value, $isMethod)) {
                return $value->$isMethod();
            }
        }

        // Strategy 4: Object method call - exact name
        if (is_object($value) && method_exists($value, $key)) {
            return $value->$key();
        }

        // Strategy 5: Object property - only try if accessible (public)
        if (is_object($value)) {
            try {
                // Use reflection to check if property is public
                $reflection = new \ReflectionClass($value);
                if ($reflection->hasProperty($key)) {
                    $property = $reflection->getProperty($key);
                    if ($property->isPublic()) {
                        return $value->$key;
                    }
                }
            } catch (\ReflectionException $e) {
                // Property doesn't exist, continue
            }
        }

        return null;
    }
}
