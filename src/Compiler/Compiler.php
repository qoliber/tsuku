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

namespace Qoliber\Tsuku\Compiler;

use Qoliber\Tsuku\Ast\CaseNode;
use Qoliber\Tsuku\Ast\DirectiveNode;
use Qoliber\Tsuku\Ast\DirectiveVisitor;
use Qoliber\Tsuku\Ast\ForNode;
use Qoliber\Tsuku\Ast\FunctionNode;
use Qoliber\Tsuku\Ast\IfNode;
use Qoliber\Tsuku\Ast\MatchNode;
use Qoliber\Tsuku\Ast\NodeVisitor;
use Qoliber\Tsuku\Ast\TemplateNode;
use Qoliber\Tsuku\Ast\TernaryNode;
use Qoliber\Tsuku\Ast\TextNode;
use Qoliber\Tsuku\Ast\VariableNode;
use Qoliber\Tsuku\DirectiveRegistry;
use Qoliber\Tsuku\Exception\DirectiveNotFoundException;
use Qoliber\Tsuku\Exception\TsukuException;
use Qoliber\Tsuku\Expression\ExpressionEvaluator;
use Qoliber\Tsuku\Function\FunctionRegistry;
use Qoliber\Tsuku\ProcessingContext;
use Qoliber\Tsuku\StrictnessMode;
use Qoliber\Tsuku\Support\DataAccessor;
use Qoliber\Tsuku\Support\Syntax;

class Compiler implements NodeVisitor, DirectiveVisitor
{
    private ExpressionEvaluator $evaluator;
    private FunctionRegistry $functionRegistry;
    private ProcessingContext $context;

    /**
     * @param array<mixed> $data
     * @param \Qoliber\Tsuku\ProcessingContext|null $context
     * @param \Qoliber\Tsuku\Function\FunctionRegistry|null $functionRegistry
     * @param \Qoliber\Tsuku\DirectiveRegistry|null $directiveRegistry Handlers of custom block directives
     */
    public function __construct(
        private array $data,
        ?ProcessingContext $context = null,
        ?FunctionRegistry $functionRegistry = null,
        private readonly ?DirectiveRegistry $directiveRegistry = null,
    ) {
        $this->functionRegistry = $functionRegistry ?? new FunctionRegistry();
        $this->evaluator = new ExpressionEvaluator($this->functionRegistry, $this->functionFailed(...));
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
        return $this->visitTemplate($ast);
    }

    /**
     * Visit template node
     *
     * @param \Qoliber\Tsuku\Ast\TemplateNode $node
     * @return string
     */
    public function visitTemplate(TemplateNode $node): string
    {
        return $this->render($node->children);
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
        $value = DataAccessor::get($this->data, $node->name);

        if ($value === null) {
            return $this->handleMissingVariable($node->name);
        }

        if (is_string($value)) {
            return $value;
        }

        return is_int($value) || is_float($value) ? (string) $value : DataAccessor::toString($value);
    }

    /**
     * Visit for loop node
     *
     * @param \Qoliber\Tsuku\Ast\ForNode $node
     * @return string
     */
    public function visitFor(ForNode $node): string
    {
        $items = DataAccessor::get($this->data, $node->collection);

        if (!is_array($items)) {
            return '';
        }

        $output = '';
        $savedData = $this->data;

        foreach ($items as $key => $item) {
            $this->data[$node->itemVar] = $item;
            if ($node->keyVar !== null) {
                $this->data[$node->keyVar] = $key;
            }

            foreach ($node->children as $child) {
                $output .= $child->accept($this);
            }
        }

        $this->data = $savedData;

        return $output;
    }

    /**
     * Visit if node (@if, @unless, @elseif chains)
     *
     * @param \Qoliber\Tsuku\Ast\IfNode $node
     * @return string
     */
    public function visitIf(IfNode $node): string
    {
        $expression = $node->expression ?? $this->evaluator->parse($node->condition);
        $result = $this->evaluator->evaluateExpression($expression, $this->data);

        if ($node->negate) {
            $result = !$result;
        }

        return $this->render($result ? $node->children : $node->elseChildren);
    }

    /**
     * Visit match node
     *
     * @param \Qoliber\Tsuku\Ast\MatchNode $node
     * @return string
     */
    public function visitMatch(MatchNode $node): string
    {
        $matchValue = $this->evaluateExpression($node->expression);

        foreach ($node->cases as $case) {
            foreach ($case->values as $caseValue) {
                // Loose comparison, as documented
                if ($matchValue == $this->evaluateExpression($caseValue)) {
                    return $this->render($case->children);
                }
            }
        }

        return $this->render($node->defaultChildren);
    }

    /**
     * Visit case node (not used directly, handled by visitMatch)
     *
     * @param \Qoliber\Tsuku\Ast\CaseNode $node
     * @return string
     */
    public function visitCase(CaseNode $node): string
    {
        return '';
    }

    /**
     * Visit ternary node (@?{condition "true" : "false"})
     *
     * @param \Qoliber\Tsuku\Ast\TernaryNode $node
     * @return string
     */
    public function visitTernary(TernaryNode $node): string
    {
        $expression = $node->expression ?? $this->evaluator->parse($node->condition);

        if ($this->evaluator->evaluateExpression($expression, $this->data)) {
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
        if (!$this->functionRegistry->has($node->name)) {
            return $this->handleMissingFunction($node->name);
        }

        try {
            $resolvedArgs = array_map(
                fn(string $arg): mixed => $this->resolveFunctionArgument($arg),
                $node->arguments
            );

            $result = $this->functionRegistry->execute($node->name, $resolvedArgs);

            return is_string($result) ? $result : DataAccessor::toString($result);
        } catch (\Throwable $e) {
            return $this->handleError("Function '{$node->name}' error: {$e->getMessage()}", $e);
        }
    }

    /**
     * Visit a custom block directive: the handler receives the rendered body
     * followed by the resolved arguments and returns the output.
     *
     * @param \Qoliber\Tsuku\Ast\DirectiveNode $node
     * @return string
     * @throws \Qoliber\Tsuku\Exception\DirectiveNotFoundException
     * @throws \Qoliber\Tsuku\Exception\TsukuException
     */
    public function visitDirective(DirectiveNode $node): string
    {
        if ($this->directiveRegistry === null || !$this->directiveRegistry->has($node->name)) {
            throw new DirectiveNotFoundException("Directive '{$node->name}' not found");
        }

        $handler = $this->directiveRegistry->get($node->name);
        $body = $this->render($node->children);

        try {
            $arguments = array_map(
                fn(string $arg): mixed => $this->resolveFunctionArgument($arg),
                $node->arguments
            );

            return DataAccessor::toString($handler($body, ...$arguments));
        } catch (\Throwable $e) {
            return $this->handleError("Directive '{$node->name}' error: {$e->getMessage()}", $e);
        }
    }

    /**
     * @param array<\Qoliber\Tsuku\Ast\Node> $children
     * @return string
     */
    private function render(array $children): string
    {
        $output = '';
        foreach ($children as $child) {
            $output .= $child->accept($this);
        }

        return $output;
    }

    /**
     * Evaluate a @match/@case expression (quoted string, number or variable path)
     *
     * @param string $expression
     * @return mixed
     */
    private function evaluateExpression(string $expression): mixed
    {
        $expression = trim($expression);

        if (
            (str_starts_with($expression, '"') && str_ends_with($expression, '"'))
            || (str_starts_with($expression, "'") && str_ends_with($expression, "'"))
        ) {
            return Syntax::unquote($expression);
        }

        if (is_numeric($expression)) {
            return str_contains($expression, '.') ? (float) $expression : (int) $expression;
        }

        return DataAccessor::get($this->data, $expression);
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
        if (preg_match('/^["\'](.*)["\']$/', $value) === 1) {
            return Syntax::unquote($value);
        }

        // Number (kept as the literal string)
        if (is_numeric($value)) {
            return $value;
        }

        // Nested function call: @functionName(args)
        $call = $this->nestedCall($value);
        if ($call !== null && $this->functionRegistry->has($call[0])) {
            return $this->functionRegistry->execute($call[0], $call[1]);
        }

        // Variable - preserve the actual type (could be array, string, number, etc.)
        return DataAccessor::get($this->data, $value) ?? '';
    }

    /**
     * Resolve a value of @?{...} - variable, string literal, number, or function call
     *
     * @param string $value
     * @return string
     */
    private function resolveValue(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^["\'](.*)["\']$/', $value) === 1) {
            return Syntax::unquote($value);
        }

        if (is_numeric($value)) {
            return $value;
        }

        if (preg_match('/^@([a-zA-Z_][a-zA-Z0-9_]*)\(/', $value, $matches) === 1) {
            // Errors are named after the outermost call, as for a function in the output
            try {
                $call = $this->nestedCall($value);
                if ($call !== null && $this->functionRegistry->has($call[0])) {
                    return DataAccessor::toString($this->functionRegistry->execute($call[0], $call[1]));
                }
            } catch (\Throwable $e) {
                return $this->handleError("Function '{$matches[1]}' error: {$e->getMessage()}", $e);
            }
        }

        return DataAccessor::toString(DataAccessor::get($this->data, $value) ?? '');
    }

    /**
     * Split "@name(args)" into the name and resolved arguments; null when $value is not a call.
     *
     * @param string $value
     * @return array{0: string, 1: array<int, mixed>}|null
     */
    private function nestedCall(string $value): ?array
    {
        if (!preg_match('/^@([a-zA-Z_][a-zA-Z0-9_]*)\(/', $value, $matches)) {
            return null;
        }

        $open = strlen($matches[0]) - 1;
        $close = Syntax::findClosingParenthesis($value, $open);
        if ($close === null) {
            return null;
        }

        $arguments = substr($value, $open + 1, $close - $open - 1);
        $resolved = [];
        if (trim($arguments) !== '') {
            foreach (Syntax::splitArguments($arguments) as $argument) {
                $resolved[] = $this->resolveFunctionArgument($argument);
            }
        }

        return [$matches[1], $resolved];
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
        return $this->handleError("Variable '{$name}' not found in data");
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
        return $this->handleError("Function '{$name}' not found");
    }

    /**
     * A function inside a condition threw: handled like a function in the output, its result is null
     *
     * @param string $name
     * @param \Throwable $error
     * @return mixed Always null
     * @throws \Qoliber\Tsuku\Exception\TsukuException In strict mode
     */
    private function functionFailed(string $name, \Throwable $error): mixed
    {
        $this->handleError("Function '{$name}' error: {$error->getMessage()}", $error);

        return null;
    }

    /**
     * Throw, record a warning or ignore, depending on the strictness mode
     *
     * @param string $message
     * @param \Throwable|null $previous The error that caused it
     * @return string Always empty (the output of the failed piece)
     * @throws \Qoliber\Tsuku\Exception\TsukuException
     */
    private function handleError(string $message, ?\Throwable $previous = null): string
    {
        match ($this->context->getStrictnessMode()) {
            StrictnessMode::STRICT => throw new TsukuException($message, 0, $previous),
            StrictnessMode::WARNING => $this->context->addWarning($message),
            StrictnessMode::SILENT => null,
        };

        return '';
    }
}
