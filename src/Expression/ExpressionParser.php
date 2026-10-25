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
use Qoliber\Tsuku\Exception\ParseException;
use Qoliber\Tsuku\Function\FunctionRegistry;

/**
 * Parses conditions of @if/@unless/@elseif and @?{...}.
 *
 * Grammar (lowest precedence first):
 *
 *   condition  := or
 *   or         := and (("or" | "||") and)*
 *   and        := not (("and" | "&&") not)*
 *   not        := ("not" | "!") not | comparison
 *   comparison := operand [("==" | "!=" | ">" | "<" | ">=" | "<=" | "in" | "not" "in") operand]
 *   operand    := string | number | path | "@" name "(" [operand ("," operand)*] ")" | "(" or ")"
 *
 * "and", "or", "not" and "in" are keywords only as whole words; paths such as
 * product.order, inventory or notes are variables. true/false/null are variable
 * paths, as in 1.3.0. Anything else is a ParseException with line and column.
 */
final class ExpressionParser
{
    private const OPERATORS = ['==', '!=', '>=', '<=', '>', '<'];
    private const KEYWORDS = ['and' => 'and', 'or' => 'or', 'not' => 'not', 'in' => 'in'];

    /** @var array<int, array{type: string, text: string, start: int, end: int}> */
    private array $tokens = [];
    private int $index = 0;
    private string $source = '';

    /** @var (callable(int): array{0: int, 1: int})|null */
    private $position = null;

    public function __construct(
        private readonly ?FunctionRegistry $functionRegistry = null,
    ) {
    }

    /**
     * Parse a condition.
     *
     * @param string $source Condition text
     * @param (callable(int): array{0: int, 1: int})|null $position Maps an offset in $source to [line, column]
     * @return \Qoliber\Tsuku\Expression\Expression
     * @throws \Qoliber\Tsuku\Exception\ParseException
     * @throws \Qoliber\Tsuku\Exception\FunctionNotFoundException
     */
    public function parseCondition(string $source, ?callable $position = null): Expression
    {
        $this->start($source, $position);

        if ($this->peek()['type'] === 'end') {
            throw $this->error('Empty condition', 0);
        }

        $expression = $this->parseOr();
        $this->expectEnd();

        return $expression;
    }

    /**
     * Parse the content of @?{condition [?] trueValue : falseValue}.
     *
     * @param string $source
     * @param (callable(int): array{0: int, 1: int})|null $position
     * @return \Qoliber\Tsuku\Expression\InlineCondition
     * @throws \Qoliber\Tsuku\Exception\ParseException
     * @throws \Qoliber\Tsuku\Exception\FunctionNotFoundException
     */
    public function parseInlineCondition(string $source, ?callable $position = null): InlineCondition
    {
        $this->start($source, $position);

        if ($this->peek()['type'] === 'end') {
            throw $this->error('Empty inline condition', 0);
        }

        $conditionStart = $this->peek()['start'];
        $expression = $this->parseOr();
        $conditionEnd = $this->tokens[$this->index - 1]['end'];

        if ($this->peek()['type'] === '?') {
            $this->index++;
        }

        $trueValue = $this->parseValueSource('the value for a true condition');
        $this->expect(':', "':' between the two values");
        $falseValue = $this->parseValueSource('the value for a false condition');
        $this->expectEnd();

        return new InlineCondition(
            $expression,
            substr($source, $conditionStart, $conditionEnd - $conditionStart),
            $trueValue,
            $falseValue
        );
    }

    /**
     * @param string $source
     * @param (callable(int): array{0: int, 1: int})|null $position
     * @return void
     * @throws \Qoliber\Tsuku\Exception\ParseException
     */
    private function start(string $source, ?callable $position): void
    {
        $this->source = $source;
        $this->index = 0;
        $this->position = $position;
        $this->tokens = $this->tokenize($source);
    }

    /**
     * Template position of an offset in the condition (the condition itself when unknown).
     *
     * @param int $offset
     * @return array{0: int, 1: int}
     */
    private function positionOf(int $offset): array
    {
        return $this->position === null ? [1, $offset + 1] : ($this->position)($offset);
    }

    /**
     * @return \Qoliber\Tsuku\Expression\Expression
     */
    private function parseOr(): Expression
    {
        $left = $this->parseAnd();
        while ($this->peek()['type'] === 'or') {
            $this->index++;
            $left = new OrExpression($left, $this->parseAnd());
        }

        return $left;
    }

    /**
     * @return \Qoliber\Tsuku\Expression\Expression
     */
    private function parseAnd(): Expression
    {
        $left = $this->parseNot();
        while ($this->peek()['type'] === 'and') {
            $this->index++;
            $left = new AndExpression($left, $this->parseNot());
        }

        return $left;
    }

    /**
     * @return \Qoliber\Tsuku\Expression\Expression
     */
    private function parseNot(): Expression
    {
        if ($this->peek()['type'] === 'not') {
            $this->index++;
            return new NotExpression($this->parseNot());
        }

        return $this->parseComparison();
    }

    /**
     * @return \Qoliber\Tsuku\Expression\Expression
     */
    private function parseComparison(): Expression
    {
        $left = $this->parseOperand(true);
        $operator = $this->comparisonOperator();
        if ($operator === null) {
            return $left;
        }

        $right = $this->parseOperand(true);
        if ($this->comparisonOperator() !== null) {
            throw $this->error(
                'Chained comparisons are not supported, combine them with "and"',
                $this->tokens[$this->index - 1]['start']
            );
        }

        return new ComparisonExpression($operator, $left, $right);
    }

    /**
     * Consume a comparison operator if one follows.
     *
     * @return string|null
     */
    private function comparisonOperator(): ?string
    {
        $token = $this->peek();

        if ($token['type'] === 'op' || $token['type'] === 'in') {
            $this->index++;
            return $token['text'];
        }

        if ($token['type'] === 'not' && $this->peek(1)['type'] === 'in') {
            $this->index += 2;
            return 'not in';
        }

        return null;
    }

    /**
     * @param bool $checkFunctions Throw when a called function is not registered
     * @return \Qoliber\Tsuku\Expression\Expression
     */
    private function parseOperand(bool $checkFunctions): Expression
    {
        $token = $this->peek();

        switch ($token['type']) {
            case 'string':
                $this->index++;
                return new LiteralExpression($token['text']);
            case 'number':
                $this->index++;
                return new LiteralExpression(
                    str_contains($token['text'], '.') ? (float) $token['text'] : (int) $token['text']
                );
            case 'path':
                $this->index++;
                return new PathExpression($token['text']);
            case 'function':
                return $this->parseFunctionCall($checkFunctions);
            case '(':
                $this->index++;
                $expression = $this->parseOr();
                $this->expect(')', "')'");
                return $expression;
        }

        throw $this->error($token['type'] === 'end' ? 'Missing value' : 'Expected a value', $token['start']);
    }

    /**
     * @param bool $checkFunctions
     * @return \Qoliber\Tsuku\Expression\FunctionCallExpression
     */
    private function parseFunctionCall(bool $checkFunctions): FunctionCallExpression
    {
        $token = $this->peek();
        $name = $token['text'];

        if ($checkFunctions && $this->functionRegistry !== null && !$this->functionRegistry->has($name)) {
            [$line, $column] = $this->positionOf($token['start']);
            throw FunctionNotFoundException::named(
                $name,
                "Unknown function '@{$name}' at line {$line}, column {$column} in condition '{$this->source}'"
            );
        }

        $this->index++;
        $this->expect('(', "'('");

        $arguments = [];
        if ($this->peek()['type'] !== ')') {
            $arguments[] = $this->parseOperand($checkFunctions);
            while ($this->peek()['type'] === ',') {
                $this->index++;
                $arguments[] = $this->parseOperand($checkFunctions);
            }
        }
        $this->expect(')', "',' or ')'");

        return new FunctionCallExpression($name, $arguments);
    }

    /**
     * Parse one value of an inline condition and return its source text.
     *
     * Values are resolved like in 1.3.0 (an unknown function renders empty), so
     * their functions are not checked.
     *
     * @param string $what
     * @return string
     */
    private function parseValueSource(string $what): string
    {
        $token = $this->peek();
        if (!in_array($token['type'], ['string', 'number', 'path', 'function'], true)) {
            throw $this->error("Expected {$what}", $token['start']);
        }

        $this->parseOperand(false);
        $end = $this->tokens[$this->index - 1]['end'];

        return substr($this->source, $token['start'], $end - $token['start']);
    }

    /**
     * @param string $type
     * @param string $description
     * @return void
     */
    private function expect(string $type, string $description): void
    {
        $token = $this->peek();
        if ($token['type'] !== $type) {
            throw $this->error("Expected {$description}", $token['start']);
        }
        $this->index++;
    }

    /**
     * @return void
     */
    private function expectEnd(): void
    {
        $token = $this->peek();
        if ($token['type'] !== 'end') {
            throw $this->error("Unexpected '{$token['text']}'", $token['start']);
        }
    }

    /**
     * @param int $ahead
     * @return array{type: string, text: string, start: int, end: int}
     */
    private function peek(int $ahead = 0): array
    {
        return $this->tokens[$this->index + $ahead] ?? $this->tokens[count($this->tokens) - 1];
    }

    /**
     * Split a condition into tokens.
     *
     * @param string $source
     * @return array<int, array{type: string, text: string, start: int, end: int}>
     * @throws \Qoliber\Tsuku\Exception\ParseException
     */
    private function tokenize(string $source): array
    {
        $tokens = [];
        $length = strlen($source);
        $offset = 0;

        while (true) {
            $offset += strspn($source, " \t\r\n", $offset);
            if ($offset >= $length) {
                $tokens[] = ['type' => 'end', 'text' => '', 'start' => $length, 'end' => $length];
                return $tokens;
            }

            $char = $source[$offset];

            if ($char === '"' || $char === "'") {
                $close = $offset;
                do {
                    $close = strpos($source, $char, $close + 1);
                } while ($close !== false && $source[$close - 1] === '\\');
                if ($close === false) {
                    throw $this->error('Unterminated string', $offset);
                }
                $tokens[] = [
                    'type' => 'string',
                    'text' => substr($source, $offset + 1, $close - $offset - 1),
                    'start' => $offset,
                    'end' => $close + 1,
                ];
                $offset = $close + 1;
                continue;
            }

            if (preg_match('/\G[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?/', $source, $matches, 0, $offset) === 1) {
                $end = $offset + strlen($matches[0]);
                if ($end < $length && preg_match('/[A-Za-z_]/', $source[$end]) === 1) {
                    throw $this->error('Invalid number', $offset);
                }
                $tokens[] = ['type' => 'number', 'text' => $matches[0], 'start' => $offset, 'end' => $end];
                $offset = $end;
                continue;
            }

            if (preg_match('/\G[a-zA-Z_][a-zA-Z0-9_.]*/', $source, $matches, 0, $offset) === 1) {
                $word = $matches[0];
                $type = self::KEYWORDS[$word] ?? 'path';
                $tokens[] = ['type' => $type, 'text' => $word, 'start' => $offset, 'end' => $offset + strlen($word)];
                $offset += strlen($word);
                continue;
            }

            if ($char === '@') {
                if (preg_match('/\G@([a-zA-Z_][a-zA-Z0-9_]*)(?=\()/', $source, $matches, 0, $offset) !== 1) {
                    throw $this->error("Expected a function call such as @name(...)", $offset);
                }
                $end = $offset + strlen($matches[0]);
                $tokens[] = ['type' => 'function', 'text' => $matches[1], 'start' => $offset, 'end' => $end];
                $offset = $end;
                continue;
            }

            $pair = substr($source, $offset, 2);
            if ($pair === '&&' || $pair === '||') {
                $tokens[] = [
                    'type' => $pair === '&&' ? 'and' : 'or',
                    'text' => $pair,
                    'start' => $offset,
                    'end' => $offset + 2,
                ];
                $offset += 2;
                continue;
            }

            $operator = $this->operatorAt($source, $offset);
            if ($operator !== null) {
                $end = $offset + strlen($operator);
                $tokens[] = ['type' => 'op', 'text' => $operator, 'start' => $offset, 'end' => $end];
                $offset = $end;
                continue;
            }

            if ($char === '!') {
                $tokens[] = ['type' => 'not', 'text' => '!', 'start' => $offset, 'end' => $offset + 1];
                $offset++;
                continue;
            }

            if (str_contains('(),?:', $char)) {
                $tokens[] = ['type' => $char, 'text' => $char, 'start' => $offset, 'end' => $offset + 1];
                $offset++;
                continue;
            }

            $hint = $char === '=' ? ' (use == to compare)' : '';
            throw $this->error("Unexpected '{$char}'{$hint}", $offset);
        }
    }

    /**
     * @param string $source
     * @param int $offset
     * @return string|null
     */
    private function operatorAt(string $source, int $offset): ?string
    {
        foreach (self::OPERATORS as $operator) {
            if (substr_compare($source, $operator, $offset, strlen($operator)) === 0) {
                return $operator;
            }
        }

        return null;
    }

    /**
     * @param string $message
     * @param int $offset
     * @return \Qoliber\Tsuku\Exception\ParseException
     */
    private function error(string $message, int $offset): ParseException
    {
        [$line, $column] = $this->positionOf($offset);

        return ParseException::at(
            "{$message} at line {$line}, column {$column} in condition '{$this->source}'",
            $line,
            $column
        );
    }
}
