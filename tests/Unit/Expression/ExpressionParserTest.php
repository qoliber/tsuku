<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit\Expression;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Exception\FunctionNotFoundException;
use Qoliber\Tsuku\Exception\ParseException;
use Qoliber\Tsuku\Expression\AndExpression;
use Qoliber\Tsuku\Expression\ComparisonExpression;
use Qoliber\Tsuku\Expression\Expression;
use Qoliber\Tsuku\Expression\ExpressionParser;
use Qoliber\Tsuku\Expression\FunctionCallExpression;
use Qoliber\Tsuku\Expression\LiteralExpression;
use Qoliber\Tsuku\Expression\NotExpression;
use Qoliber\Tsuku\Expression\OrExpression;
use Qoliber\Tsuku\Expression\PathExpression;
use Qoliber\Tsuku\Function\FunctionRegistry;

class ExpressionParserTest extends TestCase
{
    private static function dump(Expression $expression): string
    {
        return match (true) {
            $expression instanceof OrExpression => 'or(' . self::dump($expression->left) . ', '
                . self::dump($expression->right) . ')',
            $expression instanceof AndExpression => 'and(' . self::dump($expression->left) . ', '
                . self::dump($expression->right) . ')',
            $expression instanceof NotExpression => 'not(' . self::dump($expression->operand) . ')',
            $expression instanceof ComparisonExpression => $expression->operator
                . '(' . self::dump($expression->left) . ', ' . self::dump($expression->right) . ')',
            $expression instanceof FunctionCallExpression => '@' . $expression->name
                . '(' . implode(', ', array_map(self::dump(...), $expression->arguments)) . ')',
            $expression instanceof PathExpression => $expression->path,
            $expression instanceof LiteralExpression => var_export($expression->value, true),
            default => get_class($expression),
        };
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function trees(): array
    {
        return [
            'path' => ['product.price', 'product.price'],
            'path with numeric segment and trailing dot (1.3.0 accepted it)' => ['items.0.', 'items.0.'],
            'int' => ['42', '42'],
            'negative int' => ['-1', '-1'],
            'plus sign' => ['+3', '3'],
            'float' => ['1.5', '1.5'],
            'float without fraction digits' => ['5.', '5.0'],
            'float without integer part' => ['.5', '0.5'],
            'exponent is an int like in 1.3.0' => ['1e3', '1000'],
            'exponent with fraction' => ['1.5e3', '1500.0'],
            'escaped quote in a double-quoted string' => ['"a\\"b"', "'a\"b'"],
            'escaped quote in a single-quoted string' => ["'it\\'s'", "'it\\'s'"],
            'other backslashes are kept' => ['"a\\b\\\'c"', "'a\\\\b\\\\\\'c'"],
            'single-quoted string' => ["'it'", "'it'"],
            'empty string' => ['""', "''"],
            'comparison' => ['a >= 1', '>=(a, 1)'],
            'all operators' => [
                'a == 1 or a != 1 or a > 1 or a < 1 or a <= 1',
                'or(or(or(or(==(a, 1), !=(a, 1)), >(a, 1)), <(a, 1)), <=(a, 1))',
            ],
            'no spaces' => ['a>=1', '>=(a, 1)'],
            'in' => ['"x" in tags', "in('x', tags)"],
            'not in' => ['"x" not in tags', "not in('x', tags)"],
            'and before or' => ['a or b and c', 'or(a, and(b, c))'],
            'left associative and' => ['a and b and c', 'and(and(a, b), c)'],
            'symbols' => ['a || b && !c', 'or(a, and(b, not(c)))'],
            'not below comparison' => ['not a == b', 'not(==(a, b))'],
            'bang below comparison' => ['!a == b', 'not(==(a, b))'],
            'not not' => ['not not a', 'not(not(a))'],
            'parentheses' => ['(a or b) and c', 'and(or(a, b), c)'],
            'nested parentheses' => ['((a))', 'a'],
            'function' => ['@length(items) > 0', '>(@length(items), 0)'],
            'function without arguments' => ['@now()', '@now()'],
            'nested function arguments' => ['@upper(@trim(name), "x", 1.5)', "@upper(@trim(name), 'x', 1.5)"],
            'parenthesised argument' => ['@f((a or b))', '@f(or(a, b))'],
            'keywords only as whole words' => [
                'order and notes or android and inventory',
                'or(and(order, notes), and(android, inventory))',
            ],
            'keyword-like path segments' => [
                'product.in and in.stock or not.this',
                'or(and(product.in, in.stock), not.this)',
            ],
            'upper case is a path' => ['AND', 'AND'],
            'true/false/null are paths' => ['true == false or null', 'or(==(true, false), null)'],
            'whitespace and newlines' => ["\n  a\r\n  and\tb  ", 'and(a, b)'],
        ];
    }

    #[DataProvider('trees')]
    public function testParseCondition(string $source, string $expected): void
    {
        $this->assertSame($expected, self::dump((new ExpressionParser())->parseCondition($source)));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function errors(): array
    {
        return [
            'empty' => ['  ', 'Empty condition at line 1, column 1'],
            'missing right operand' => ['a ==', 'Missing value at line 1, column 5'],
            'operator first' => ['== a', 'Expected a value at line 1, column 1'],
            'adjacent operands' => ['a b', "Unexpected 'b' at line 1, column 3"],
            'chained comparison' => [
                'a < b < c',
                'Chained comparisons are not supported, combine them with "and" at line 1, column 7',
            ],
            'chained in' => ['a in b in c', 'Chained comparisons'],
            'single equals' => ['a = 1', "Unexpected '=' (use == to compare) at line 1, column 3"],
            'triple equals' => ['a === 1', "Unexpected '='"],
            'single ampersand' => ['a & b', "Unexpected '&' at line 1, column 3"],
            'single pipe' => ['a | b', "Unexpected '|'"],
            'arithmetic' => ['a + 1', "Unexpected '+' at line 1, column 3"],
            'unterminated double quote' => ['a == "x', 'Unterminated string at line 1, column 6'],
            'unterminated single quote' => ["a == 'x\\'", 'Unterminated string'],
            'mismatched quotes' => ["a == \"x'", 'Unterminated string'],
            'unclosed parenthesis' => ['(a or b', "Expected ')' at line 1, column 8"],
            'stray closing parenthesis' => ['a)', "Unexpected ')'"],
            'missing argument separator' => ['@f(a b)', "Expected ',' or ')' at line 1, column 6"],
            'trailing comma' => ['@f(a,)', 'Expected a value at line 1, column 6'],
            'leading comma' => ['@f(,a)', 'Expected a value at line 1, column 4'],
            'operator in argument' => ['@f(a > 1)', "Expected ',' or ')'"],
            'function without parentheses' => ['@f', 'Expected a function call such as @name(...) at line 1, column 1'],
            'function with space before parenthesis' => ['@f (a)', 'Expected a function call'],
            'escaped at' => ['@@', 'Expected a function call'],
            'number followed by letters' => ['a > 5px', 'Invalid number at line 1, column 5'],
            'not without in after operand' => ['a not b', "Unexpected 'not'"],
            'question mark in condition' => ['a ? b', "Unexpected '?'"],
            'colon in condition' => ['a : b', "Unexpected ':'"],
            'dangling not' => ['not', 'Missing value at line 1, column 4'],
            'dangling or' => ['a or', 'Missing value'],
            'dangling and' => ['a &&', 'Missing value'],
        ];
    }

    #[DataProvider('errors')]
    public function testErrors(string $source, string $message): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage($message);

        (new ExpressionParser())->parseCondition($source);
    }

    public function testErrorMessageNamesTheCondition(): void
    {
        $this->expectExceptionMessage("in condition 'a b'");

        (new ExpressionParser())->parseCondition('a b');
    }

    public function testPositionCallbackMapsOffsets(): void
    {
        $seen = [];
        $position = function (int $offset) use (&$seen): array {
            $seen[] = $offset;
            return [7, 100 + $offset];
        };

        try {
            (new ExpressionParser())->parseCondition('a and b c', $position);
            $this->fail('expected ParseException');
        } catch (ParseException $e) {
            $this->assertSame([8], $seen);
            $this->assertSame([7, 108], [$e->getTemplateLine(), $e->getTemplateColumn()]);
            $this->assertStringContainsString('at line 7, column 108', $e->getMessage());
        }
    }

    public function testDefaultPositionIsRestoredForTheNextCall(): void
    {
        $parser = new ExpressionParser();
        try {
            $parser->parseCondition('a b', fn(int $offset): array => [9, 9]);
        } catch (ParseException) {
        }

        $this->expectExceptionMessage('at line 1, column 3');
        $parser->parseCondition('a b');
    }

    public function testParserCanBeReused(): void
    {
        $parser = new ExpressionParser();

        $this->assertSame('a', self::dump($parser->parseCondition('a')));
        $this->assertSame('and(b, c)', self::dump($parser->parseCondition('b and c')));
    }

    public function testUnknownFunctionIsRejectedWhenARegistryIsGiven(): void
    {
        $parser = new ExpressionParser(new FunctionRegistry());

        $this->assertSame('@length(x)', self::dump($parser->parseCondition('@length(x)')));

        try {
            $parser->parseCondition('@length(@nope(x))', fn(int $offset): array => [3, $offset + 5]);
            $this->fail('expected FunctionNotFoundException');
        } catch (FunctionNotFoundException $e) {
            $this->assertSame('nope', $e->getFunctionName());
            $this->assertSame(
                "Unknown function '@nope' at line 3, column 13 in condition '@length(@nope(x))'",
                $e->getMessage()
            );
        }
    }

    public function testUnknownFunctionIsAcceptedWithoutRegistry(): void
    {
        $this->assertSame('@nope(x)', self::dump((new ExpressionParser())->parseCondition('@nope(x)')));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    public static function inlineConditions(): array
    {
        return [
            'strings' => ['stock > 0 "In Stock" : "Out"', '>(stock, 0)', 'stock > 0', '"In Stock"', '"Out"'],
            'question mark' => ['a ? b : c', 'a', 'a', 'b', 'c'],
            'numbers' => ['a 1.50 : 0', 'a', 'a', '1.50', '0'],
            'functions' => [
                'a and b @upper(x) : @lower(@trim(y))',
                'and(a, b)',
                'a and b',
                '@upper(x)',
                '@lower(@trim(y))',
            ],
            'colon in string' => ['a == "x:y" "1:2" : "3"', "==(a, 'x:y')", 'a == "x:y"', '"1:2"', '"3"'],
            'spaces' => ['  a   "b"   :   c  ', 'a', 'a', '"b"', 'c'],
        ];
    }

    #[DataProvider('inlineConditions')]
    public function testParseInlineCondition(
        string $source,
        string $tree,
        string $condition,
        string $true,
        string $false
    ): void {
        $inline = (new ExpressionParser())->parseInlineCondition($source);

        $this->assertSame($tree, self::dump($inline->expression));
        $this->assertSame($condition, $inline->condition);
        $this->assertSame($true, $inline->trueValue);
        $this->assertSame($false, $inline->falseValue);
    }

    public function testInlineConditionValuesDoNotCheckFunctions(): void
    {
        $inline = (new ExpressionParser(new FunctionRegistry()))->parseInlineCondition('a @nope(x) : "b"');

        $this->assertSame('@nope(x)', $inline->trueValue);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function inlineErrors(): array
    {
        return [
            'empty' => [' ', 'Empty inline condition'],
            'no values' => ['a', 'Expected the value for a true condition at line 1, column 2'],
            'no colon' => ['a "b" "c"', "Expected ':' between the two values at line 1, column 7"],
            'no false value' => ['a "b" :', 'Expected the value for a false condition'],
            'parenthesised value' => ['a (b) : c', 'Expected the value for a true condition'],
            'extra value' => ['a b : c d', "Unexpected 'd'"],
            'keyword as value' => ['a in : c', 'Expected a value'],
        ];
    }

    #[DataProvider('inlineErrors')]
    public function testInlineConditionErrors(string $source, string $message): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage($message);

        (new ExpressionParser())->parseInlineCondition($source);
    }
}
