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

namespace Qoliber\Tsuku\Compiler;

use Qoliber\Tsuku\Ast\NodeVisitor;
use Qoliber\Tsuku\Ast\TemplateNode;
use Qoliber\Tsuku\Ast\TextNode;
use Qoliber\Tsuku\Ast\VariableNode;
use Qoliber\Tsuku\Ast\ForNode;
use Qoliber\Tsuku\Ast\IfNode;
use Qoliber\Tsuku\Ast\MatchNode;
use Qoliber\Tsuku\Ast\CaseNode;
use Qoliber\Tsuku\Ast\TernaryNode;
use Qoliber\Tsuku\Ast\FunctionNode;
use Qoliber\Tsuku\Expression\ExpressionEvaluator;
use Qoliber\Tsuku\Function\FunctionRegistry;
use Qoliber\Tsuku\ProcessingContext;
use Qoliber\Tsuku\StrictnessMode;
use Qoliber\Tsuku\Exception\TsukuException;

class Compiler implements NodeVisitor
{
    private ExpressionEvaluator $evaluator;
    private FunctionRegistry $functionRegistry;
    private ProcessingContext $context;

    /**
     * @param array<mixed> $data
     * @param \Qoliber\Tsuku\ProcessingContext|null $context
     * @param \Qoliber\Tsuku\Function\FunctionRegistry|null $functionRegistry
     */
    public function __construct(
        private array $data,
        ?ProcessingContext $context = null,
        ?FunctionRegistry $functionRegistry = null,
    ) {
        $this->functionRegistry = $functionRegistry ?? new FunctionRegistry();
        $this->evaluator = new ExpressionEvaluator($this->functionRegistry);
        $this->context = $context ?? new ProcessingContext(StrictnessMode::SILENT);
    }

    /**
     * Compile AST to string
     *
     * @param \Qoliber\Tsuku\Ast\TemplateNode $ast
     * @return string
     */
    public function compile(TemplateNode $ast): string
    {
        return $ast->accept($this);
    }

    /**
     * Visit template node
     *
     * @param \Qoliber\Tsuku\Ast\TemplateNode $node
     * @return string
     */
    public function visitTemplate(TemplateNode $node): string
    {
        $output = '';
        foreach ($node->children as $child) {
            $output .= $child->accept($this);
        }
        return $output;
    }

    /**
     * Visit text node
     *
     * @param \Qoliber\Tsuku\Ast\TextNode $node
     * @return string
     */
    public function visitText(TextNode $node): string
    {
        return $node->content;
    }

    /**
     * Visit variable node
     *
     * @param \Qoliber\Tsuku\Ast\VariableNode $node
     * @return string
     * @throws \Qoliber\Tsuku\Exception\TsukuException
     */
    public function visitVariable(VariableNode $node): string
    {
        $value = $this->getNestedValue($this->data, $node->name);

        if ($value === null) {
            return $this->handleMissingVariable($node->name);
        }

        return (string) $value;
    }

    /**
     * Visit for loop node
     *
     * @param \Qoliber\Tsuku\Ast\ForNode $node
     * @return string
     */
    public function visitFor(ForNode $node): string
    {
        $items = $this->getNestedValue($this->data, $node->collection);

        if (!is_array($items)) {
            return '';
        }

        $output = '';
        $savedData = $this->data;

        foreach ($items as $key => $item) {
            // Set iteration variables
            $this->data[$node->itemVar] = $item;
            if ($node->keyVar !== null) {
                $this->data[$node->keyVar] = $key;
            }

            // Execute children
            foreach ($node->children as $child) {
                $output .= $child->accept($this);
            }
        }

        // Restore original data
        $this->data = $savedData;

        return $output;
    }

    /**
     * Visit if node
     *
     * @param \Qoliber\Tsuku\Ast\IfNode $node
     * @return string
     */
    public function visitIf(IfNode $node): string
    {
        $result = $this->evaluator->evaluate($node->condition, $this->data);

        // Apply negation for unless
        if ($node->negate) {
            $result = !$result;
        }

        $output = '';

        if ($result) {
            // Execute if/unless children
            foreach ($node->children as $child) {
                $output .= $child->accept($this);
            }
        } else {
            // Execute else children
            foreach ($node->elseChildren as $child) {
                $output .= $child->accept($this);
            }
        }

        return $output;
    }

    /**
     * Visit match node
     *
     * @param \Qoliber\Tsuku\Ast\MatchNode $node
     * @return string
     */
    public function visitMatch(MatchNode $node): string
    {
        // Evaluate the match expression
        $matchValue = $this->evaluateExpression($node->expression);

        // Try each case
        foreach ($node->cases as $case) {
            // Check if any of the case values match
            foreach ($case->values as $caseValue) {
                $evaluatedCaseValue = $this->evaluateExpression($caseValue);

                // Use loose comparison for flexibility
                if ($matchValue == $evaluatedCaseValue) {
                    // Execute this case's children
                    $output = '';
                    foreach ($case->children as $child) {
                        $output .= $child->accept($this);
                    }
                    return $output;
                }
            }
        }

        // No case matched, execute default if present
        $output = '';
        foreach ($node->defaultChildren as $child) {
            $output .= $child->accept($this);
        }
        return $output;
    }

    /**
     * Visit case node (not used directly, handled by visitMatch)
     *
     * @param \Qoliber\Tsuku\Ast\CaseNode $node
     * @return string
     */
    public function visitCase(CaseNode $node): string
    {
        // Case nodes are handled by visitMatch
        return '';
    }

    /**
     * Evaluate an expression (variable path or literal value)
     *
     * @param string $expression
     * @return mixed
     */
    private function evaluateExpression(string $expression): mixed
    {
        $expression = trim($expression);

        // Check if it's a quoted string
        if ((str_starts_with($expression, '"') && str_ends_with($expression, '"'))
            || (str_starts_with($expression, "'") && str_ends_with($expression, "'"))) {
            return substr($expression, 1, -1);
        }

        // Check if it's a number
        if (is_numeric($expression)) {
            return str_contains($expression, '.') ? (float) $expression : (int) $expression;
        }

        // Otherwise, treat as variable path
        return $this->getNestedValue($this->data, $expression);
    }

    /**
     * Visit ternary node
     *
     * @param \Qoliber\Tsuku\Ast\TernaryNode $node
     * @return string
     */
    public function visitTernary(TernaryNode $node): string
    {
        $result = $this->evaluator->evaluate($node->condition, $this->data);

        if ($result) {
            return $this->resolveValue($node->trueValue);
        }

        return $this->resolveValue($node->falseValue);
    }

    /**
     * Visit function node
     *
     * @param \Qoliber\Tsuku\Ast\FunctionNode $node
     * @return string
     * @throws \Qoliber\Tsuku\Exception\TsukuException
     */
    public function visitFunction(FunctionNode $node): string
    {
        // Check if function exists
        if (!$this->functionRegistry->has($node->name)) {
            return $this->handleMissingFunction($node->name);
        }

        try {
            $resolvedArgs = array_map(
                fn($arg) => $this->resolveFunctionArgument($arg),
                $node->arguments
            );

            $result = $this->functionRegistry->execute($node->name, $resolvedArgs);
            return (string) $result;
        } catch (\Throwable $e) {
            return $this->handleFunctionError($node->name, $e->getMessage());
        }
    }

    /**
     * Resolve a function argument - preserves type (can be array, string, number, etc.)
     *
     * @param string $value
     * @return mixed
     */
    private function resolveFunctionArgument(string $value): mixed
    {
        $value = trim($value);

        // String literal with quotes
        if (preg_match('/^["' . "'" . '](.*)["' . "'" . ']$/', $value, $matches)) {
            return $matches[1];
        }

        // Number
        if (is_numeric($value)) {
            return $value;
        }

        // Nested function call: @functionName(args)
        if (preg_match('/^@([a-zA-Z_][a-zA-Z0-9_]*)\(/', $value, $matches)) {
            $functionName = $matches[1];
            $argsStart = strlen($matches[0]) - 1;
            $args = $this->extractBalancedParentheses($value, $argsStart);

            if ($args !== null) {
                // Parse arguments
                $arguments = [];
                if (trim($args) !== '') {
                    $arguments = $this->parseArguments($args);
                }

                // Recursively resolve arguments
                $resolvedArgs = array_map(
                    fn($arg) => $this->resolveFunctionArgument($arg),
                    $arguments
                );

                // Execute nested function
                if ($this->functionRegistry->has($functionName)) {
                    return $this->functionRegistry->execute($functionName, $resolvedArgs);
                }
            }
        }

        // Variable - preserve the actual type (could be array, string, number, etc.)
        $varValue = $this->getNestedValue($this->data, $value);
        return $varValue !== null ? $varValue : '';
    }

    /**
     * Resolve a value - can be a variable, string literal, number, or function call
     *
     * @param string $value
     * @return string
     */
    private function resolveValue(string $value): string
    {
        $value = trim($value);

        // String literal with quotes
        if (preg_match('/^["' . "'" . '](.*)["' . "'" . ']$/', $value, $matches)) {
            return $matches[1];
        }

        // Number
        if (is_numeric($value)) {
            return $value;
        }

        // Function call: @functionName(args)
        if (preg_match('/^@([a-zA-Z_][a-zA-Z0-9_]*)\(/', $value, $matches)) {
            $functionName = $matches[1];
            $argsStart = strlen($matches[0]) - 1;
            $args = $this->extractBalancedParentheses($value, $argsStart);

            if ($args !== null && $this->functionRegistry->has($functionName)) {
                // Parse arguments
                $arguments = [];
                if (trim($args) !== '') {
                    $arguments = $this->parseArguments($args);
                }

                // Resolve arguments
                $resolvedArgs = array_map(
                    fn($arg) => $this->resolveFunctionArgument($arg),
                    $arguments
                );

                $result = $this->functionRegistry->execute($functionName, $resolvedArgs);
                return (string) $result;
            }
        }

        // Variable
        $varValue = $this->getNestedValue($this->data, $value);
        return $varValue !== null ? (string) $varValue : '';
    }

    /**
     * Extract content between balanced parentheses
     *
     * @param string $text The text to parse
     * @param int $startPos Position of the opening parenthesis
     * @return string|null The content between parentheses, or null if unbalanced
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

        return null; // Unbalanced parentheses
    }

    /**
     * Parse function arguments, respecting quoted strings
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

    /**
     * Handle missing variable based on strictness mode
     *
     * @param string $name
     * @return string
     * @throws \Qoliber\Tsuku\Exception\TsukuException
     */
    private function handleMissingVariable(string $name): string
    {
        $message = "Variable '{$name}' not found in data";

        match ($this->context->getStrictnessMode()) {
            StrictnessMode::STRICT => throw new TsukuException($message),
            StrictnessMode::WARNING => $this->context->addWarning($message),
            StrictnessMode::SILENT => null,
        };

        return '';
    }

    /**
     * Handle missing function based on strictness mode
     *
     * @param string $name
     * @return string
     * @throws \Qoliber\Tsuku\Exception\TsukuException
     */
    private function handleMissingFunction(string $name): string
    {
        $message = "Function '{$name}' not found";

        match ($this->context->getStrictnessMode()) {
            StrictnessMode::STRICT => throw new TsukuException($message),
            StrictnessMode::WARNING => $this->context->addWarning($message),
            StrictnessMode::SILENT => null,
        };

        return '';
    }

    /**
     * Handle function execution error based on strictness mode
     *
     * @param string $name
     * @param string $error
     * @return string
     * @throws \Qoliber\Tsuku\Exception\TsukuException
     */
    private function handleFunctionError(string $name, string $error): string
    {
        $message = "Function '{$name}' error: {$error}";

        match ($this->context->getStrictnessMode()) {
            StrictnessMode::STRICT => throw new TsukuException($message),
            StrictnessMode::WARNING => $this->context->addWarning($message),
            StrictnessMode::SILENT => null,
        };

        return '';
    }
}
