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
use Qoliber\Tsuku\Expression\ExpressionEvaluator;
use Qoliber\Tsuku\Expression\FunctionCallExpression;
use Qoliber\Tsuku\Expression\LiteralExpression;
use Qoliber\Tsuku\Expression\NotExpression;
use Qoliber\Tsuku\Expression\OrExpression;
use Qoliber\Tsuku\Expression\PathExpression;
use Qoliber\Tsuku\Function\FunctionRegistry;

class ExpressionEvaluatorTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: string, 2: mixed, 3: bool}>
     */
    public static function comparisons(): array
    {
        return [
            'greater' => [5, '>', 3, true],
            'greater equal' => [3, '>', 3, false],
            'less' => [2, '<', 3, true],
            'less equal' => [3, '<', 3, false],
            'greater or equal' => [3, '>=', 3, true],
            'greater or equal false' => [2, '>=', 3, false],
            'less or equal' => [3, '<=', 3, true],
            'less or equal false' => [4, '<=', 3, false],
            'loose equal numeric strings' => ['05', '==', 5, true],
            'loose equal null and empty' => [null, '==', '', true],
            'loose not equal' => ['abc', '!=', 0, true],
            'not equal false' => [1, '!=', '1', false],
            'numeric strings compare as numbers' => ['10', '>', '9', true],
            'in array' => ['7231', 'in', [7231, 12], true],
            'in array missing' => ['723', 'in', [7231, 12], false],
            'in string' => ['23', 'in', '7231', true],
            'not in array' => ['x', 'not in', ['y'], true],
            'not in string false' => ['72', 'not in', '7231', false],
        ];
    }

    #[DataProvider('comparisons')]
    public function testCompare(mixed $left, string $operator, mixed $right, bool $expected): void
    {
        $this->assertSame($expected, ExpressionEvaluator::compare($left, $operator, $right));
    }

    public function testUnsupportedOperator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported operator '==='");

        ExpressionEvaluator::compare(1, '===', 1);
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function truthiness(): array
    {
        return [
            'null' => [null, false],
            'false' => [false, false],
            'zero' => [0, false],
            'zero float' => [0.0, false],
            'empty string' => ['', false],
            'string zero' => ['0', false],
            'empty array' => [[], false],
            'true' => [true, true],
            'one' => [1, true],
            'negative' => [-1, true],
            'string' => ['a', true],
            'string 0.0' => ['0.0', true],
            'array' => [[0], true],
            'object' => [new \stdClass(), true],
        ];
    }

    #[DataProvider('truthiness')]
    public function testIsTruthy(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, ExpressionEvaluator::isTruthy($value));
    }

    public function testEvaluateString(): void
    {
        $evaluator = new ExpressionEvaluator(new FunctionRegistry());
        $data = ['a' => ['b' => 3], 'tags' => ['x']];

        $this->assertTrue($evaluator->evaluate('a.b > 2 and "x" in tags', $data));
        $this->assertFalse($evaluator->evaluate('a.b > 2 and "y" in tags', $data));
        $this->assertTrue($evaluator->evaluate('@length(tags) == 1', $data));
        $this->assertTrue($evaluator->evaluate('missing == 0', $data));
    }

    public function testEvaluateStringThrowsOnMalformedInput(): void
    {
        $this->expectException(ParseException::class);

        (new ExpressionEvaluator())->evaluate('a ==', []);
    }

    public function testParseIsCached(): void
    {
        $evaluator = new ExpressionEvaluator();

        $this->assertSame($evaluator->parse('a and b'), $evaluator->parse('a and b'));
        $this->assertNotSame($evaluator->parse('a and b'), $evaluator->parse('a or b'));
    }

    public function testEvaluateExpressionNodes(): void
    {
        $evaluator = new ExpressionEvaluator(new FunctionRegistry());
        $data = ['n' => 2, 'name' => ' Ab '];

        $this->assertTrue($evaluator->evaluateExpression(new PathExpression('n'), $data));
        $this->assertFalse($evaluator->evaluateExpression(new PathExpression('missing'), $data));
        $this->assertFalse($evaluator->evaluateExpression(new LiteralExpression('0'), $data));
        $this->assertTrue($evaluator->evaluateExpression(new NotExpression(new PathExpression('missing')), $data));
        $this->assertTrue($evaluator->evaluateExpression(
            new ComparisonExpression(
                '==',
                new FunctionCallExpression('trim', [new PathExpression('name')]),
                new LiteralExpression('Ab')
            ),
            $data
        ));
    }

    public function testLogicalNodesShortCircuit(): void
    {
        $evaluator = new ExpressionEvaluator();
        $exploding = new class implements Expression {
            public function evaluate(ExpressionEvaluator $evaluator, array $data): mixed
            {
                throw new \LogicException('evaluated');
            }
        };

        $this->assertFalse((new AndExpression(new LiteralExpression(0), $exploding))->evaluate($evaluator, []));
        $this->assertTrue((new OrExpression(new LiteralExpression(1), $exploding))->evaluate($evaluator, []));
        $one = new LiteralExpression(1);
        $zero = new LiteralExpression(0);
        $this->assertTrue((new AndExpression($one, new LiteralExpression('x')))->evaluate($evaluator, []));
        $this->assertFalse((new OrExpression(new LiteralExpression(''), $zero))->evaluate($evaluator, []));
    }

    public function testFunctionArgumentsAreEvaluatedInOrder(): void
    {
        $registry = new FunctionRegistry();
        $registry->register(
            'pair',
            fn(mixed $a, mixed $b): string => var_export($a, true) . '|' . var_export($b, true)
        );
        $evaluator = new ExpressionEvaluator($registry);

        $call = new FunctionCallExpression('pair', [new LiteralExpression(1.5), new PathExpression('missing')]);

        $this->assertSame('1.5|NULL', $call->evaluate($evaluator, []));
    }

    public function testCallFunctionWithoutRegistry(): void
    {
        $this->expectException(FunctionNotFoundException::class);
        $this->expectExceptionMessage("Unknown function '@upper' in condition");

        (new ExpressionEvaluator())->callFunction('upper', ['a']);
    }

    public function testCallUnknownFunction(): void
    {
        try {
            (new ExpressionEvaluator(new FunctionRegistry()))->evaluate('@nope(1)', []);
            $this->fail('expected FunctionNotFoundException');
        } catch (FunctionNotFoundException $e) {
            $this->assertSame('nope', $e->getFunctionName());
        }
    }

    public function testCallFunction(): void
    {
        $this->assertSame('AB', (new ExpressionEvaluator(new FunctionRegistry()))->callFunction('upper', ['ab']));
    }
}
