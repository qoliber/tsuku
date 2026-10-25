<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Exception\FunctionNotFoundException;
use Qoliber\Tsuku\Exception\ParseException;
use Qoliber\Tsuku\Exception\TsukuException;
use Qoliber\Tsuku\StrictnessMode;
use Qoliber\Tsuku\Tsuku;

/**
 * Boolean expressions in @if, @unless, @elseif and @?{...}.
 */
class ConditionExpressionTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function data(): array
    {
        return [
            'p' => ['qty' => 5, 'sku' => 'A', 'groups' => '7231,12', 'ids' => [7231, 12], 'tags' => ['new', 'sale']],
            'product' => ['order' => 2, 'notes' => 'fragile', 'in' => 1],
            'inventory' => 3,
            'android' => 'yes',
            'notes' => '',
            'order' => 0,
            'empty' => [],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function conditions(): array
    {
        return [
            // and / or (brief)
            'and evaluates both sides' => ['p.qty > 0 and p.sku == "B"', false],
            '&& evaluates both sides' => ['p.qty > 0 && p.sku == "B"', false],
            'and true' => ['p.qty > 0 and p.sku == "A"', true],
            'or evaluates both sides' => ['p.qty < 0 or p.sku == "A"', true],
            '|| evaluates both sides' => ['p.qty < 0 || p.sku == "A"', true],
            'or false' => ['p.qty < 0 or p.sku == "B"', false],

            // precedence: or < and < not < comparison
            'and binds tighter than or' => ['p.qty < 0 and p.sku == "B" or p.sku == "A"', true],
            'and binds tighter than or (right)' => ['p.sku == "A" or p.qty < 0 and p.sku == "B"', true],
            'parentheses override precedence' => ['(p.sku == "A" or p.qty < 0) and p.sku == "B"', false],
            'not binds looser than comparison' => ['not p.qty == 5', false],
            '! binds looser than comparison' => ['!p.qty == 4', true],
            'not before and' => ['not p.qty < 0 and p.sku == "A"', true],
            'double not' => ['not not p.sku', true],
            'not of parenthesised or' => ['!(p.qty < 0 || p.sku == "B")', true],

            // in / not in
            'in string is substring' => ['"7231" in p.groups', true],
            'in string is case-sensitive' => ['"a" in p.sku', false],
            'in string substring of an id' => ['"723" in p.groups', true],
            'in split is exact membership' => ['"723" in @split(p.groups, ",")', false],
            'in split exact id' => ['"7231" in @split(p.groups, ",")', true],
            'in array compares as strings' => ['"7231" in p.ids', true],
            'number in array' => ['12 in p.ids', true],
            'in array of strings' => ['"sale" in p.tags', true],
            'not in' => ['"old" not in p.tags', true],
            'not in (false)' => ['"new" not in p.tags', false],
            'in on missing value' => ['"x" in missing', false],
            'in on a number is false' => ['5 in p.qty', false],
            'variable in literal string' => ['p.sku in "ABC"', true],

            // keywords only as whole words
            'product.order is a variable' => ['product.order == 2', true],
            'inventory is a variable' => ['inventory > 2', true],
            'android is a variable' => ['android', true],
            'notes is a variable' => ['notes', false],
            'order is a variable' => ['order', false],
            'product.in is a variable' => ['product.in', true],
            'keyword inside a string' => ['p.sku != "a and b or not c in d"', true],

            // operands
            'function call alone' => ['@length(p.tags)', true],
            'function call alone (empty)' => ['@length(empty)', false],
            'contains function' => ['@contains(p.tags, "new")', true],
            'contains function on string' => ['@contains(p.groups, "7231") and p.qty >= 5', true],
            'nested functions' => ['@upper(@trim(p.sku)) == "A"', true],
            'string literal alone' => ['"x"', true],
            'string zero alone' => ['"0"', false],
            'number alone' => ['1', true],
            'number zero alone' => ['0', false],
            'negative number' => ['p.qty > -1', true],
            'exponent number' => ['p.qty < 1e1', true],
            'single quotes' => ["p.sku == 'A'", true],
            'parenthesised operand' => ['(p.qty) == 5', true],

            // short-circuit: the right side would throw if evaluated
            'and short-circuits' => ['p.qty < 0 and @explode(p.sku)', false],
            'or short-circuits' => ['p.qty > 0 or @explode(p.sku)', true],
        ];
    }

    #[DataProvider('conditions')]
    public function testCondition(string $condition, bool $expected): void
    {
        $tsuku = new Tsuku();
        $tsuku->registerFunction('explode', function (): never {
            throw new \RuntimeException('must not be evaluated');
        });

        $this->assertSame($expected ? 'Y' : ' N', $tsuku->process("@if({$condition})Y@else N@end", self::data()));
        $this->assertSame($expected ? ' N' : 'Y', $tsuku->process("@unless({$condition})Y@else N@end", self::data()));
        $this->assertSame($expected ? 'Y' : 'N', $tsuku->process("@?{{$condition} \"Y\" : \"N\"}", self::data()));
    }

    public function testElseifChain(): void
    {
        $template = '@if(p.qty > 10)lots@elseif(p.qty > 3 and "sale" in p.tags)some on sale'
            . '@elseif(p.qty > 0)some@else none@end';

        $this->assertSame('some on sale', (new Tsuku())->process($template, self::data()));
        $this->assertSame(' none', (new Tsuku())->process($template, ['p' => ['qty' => 0]]));
        $this->assertSame('some', (new Tsuku())->process($template, ['p' => ['qty' => 1, 'tags' => []]]));
    }

    public function testElseifAfterUnless(): void
    {
        $template = '@unless(p.qty)none@elseif(p.qty > 3)many@else few@end';

        $this->assertSame('many', (new Tsuku())->process($template, self::data()));
        $this->assertSame('none', (new Tsuku())->process($template, ['p' => ['qty' => 0]]));
        $this->assertSame(' few', (new Tsuku())->process($template, ['p' => ['qty' => 2]]));
    }

    public function testInlineConditionWithOldQuestionMarkSyntax(): void
    {
        $this->assertSame('in', (new Tsuku())->process('@?{p.qty > 0 and p.sku ? "in" : "out"}', self::data()));
    }

    public function testInlineConditionValuesAreResolvedAsIn130(): void
    {
        $tsuku = new Tsuku();

        $this->assertSame('A', $tsuku->process('@?{p.qty > 0 p.sku : "none"}', self::data()));
        $this->assertSame('1.50', $tsuku->process('@?{p.qty > 0 1.50 : 0}', self::data()));
        $this->assertSame('', $tsuku->process('@?{p.qty > 0 @nosuchfn(p.sku) : "x"}', self::data()));
        $this->assertSame('NEW', $tsuku->process('@?{"7231" in p.groups @upper(@first(p.tags)) : "x"}', self::data()));
        $this->assertSame('a}b', $tsuku->process('@?{p.qty > 0 "a}b" : "c"}', self::data()));
    }

    public function testDirectiveWithSpaceBeforeParenthesis(): void
    {
        // 1.3.0 left a stray ")" in the output
        $this->assertSame('Y', (new Tsuku())->process('@if (p.qty > 0)Y@else N@end', self::data()));
        $this->assertSame(
            '5;',
            (new Tsuku())->process('@for (p.ids as id)@unless (id > 100){id};@end@end', ['p' => ['ids' => [5]]])
        );
        $this->assertSame('a,b,', (new Tsuku())->process('@for (items as i){i},@end', ['items' => ['a', 'b']]));
    }

    public function testParenthesisInsideStringDoesNotCloseTheDirective(): void
    {
        $this->assertSame('Y', (new Tsuku())->process('@if(p.sku != ")")Y@end', self::data()));
        $this->assertSame('a[b]', (new Tsuku())->process('@replace(@replace(s, "(", "["), ")", "]")', ['s' => 'a(b)']));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function malformed(): array
    {
        return [
            'single equals' => ['@if(p.qty = 5)Y@end', "Unexpected '=' (use == to compare) at line 1, column 11"],
            'dangling operator' => ['@if(p.qty >)Y@end', 'Missing value at line 1, column 12'],
            'dangling and' => ['@if(p.qty > 0 and)Y@end', 'Missing value at line 1, column 18'],
            'two operands' => ['@if(p.qty 5)Y@end', "Unexpected '5' at line 1, column 11"],
            'chained comparison' => ['@if(1 < p.qty < 9)Y@end', 'Chained comparisons are not supported'],
            'strict equality' => ['@if(p.qty === 5)Y@end', "Unexpected '=' (use == to compare) at line 1, column 13"],
            'arithmetic' => ['@if(p.qty - 1 > 0)Y@end', "Unexpected '-' at line 1, column 11"],
            'unclosed parenthesis' => ['@if((p.qty > 0)Y@end', 'Unclosed parenthesis after @if at line 1, column 1'],
            'missing closing parenthesis in group' => ['@if((p.qty p.sku))Y@end', "Expected ')' at line 1, column 12"],
            'unterminated string' => ['@if(p.sku == "A)Y@end', 'Unterminated string'],
            'empty condition' => ['@if()Y@end', 'Empty condition at line 1, column 5'],
            'not without in' => ['@if(p.sku not "A")Y@end', "Unexpected 'not'"],
            'function without parentheses' => ['@if(@length)Y@end', 'Expected a function call'],
            'bad number' => ['@if(p.qty > 5abc)Y@end', 'Invalid number at line 1, column 13'],
            'inline condition without colon' => ['@?{p.qty > 0 "a" "b"}', "Expected ':' between the two values"],
            'inline condition without true value' => ['@?{p.qty > 0 : "b"}', 'Expected the value for a true condition'],
            'inline condition trailing value' => ['@?{p.qty > 0 "a" : "b" "c"}', "Unexpected 'c'"],
            'elseif condition' => ["@if(x)a\n@elseif(p.qty >)b@end", 'Missing value at line 2, column 16'],
            'unless condition on a later line' => [
                "\n\n  @unless(p.qty ! 5)x@end",
                "Unexpected '!' at line 3, column 17",
            ],
        ];
    }

    #[DataProvider('malformed')]
    public function testMalformedConditionThrowsWithPosition(string $template, string $message): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage($message);

        (new Tsuku())->process($template, self::data());
    }

    public function testParseExceptionExposesLineAndColumn(): void
    {
        try {
            (new Tsuku())->process("line one\\\ncontinued\n@if(a b)x@end", []);
            $this->fail('expected ParseException');
        } catch (ParseException $e) {
            $this->assertSame(3, $e->getTemplateLine());
            $this->assertSame(7, $e->getTemplateColumn());
        }
    }

    public function testUnknownFunctionInConditionThrows(): void
    {
        try {
            (new Tsuku())->process("ok\n@if(@nosuchfn(p.sku))Y@else N@end", self::data());
            $this->fail('expected FunctionNotFoundException');
        } catch (FunctionNotFoundException $e) {
            $this->assertInstanceOf(TsukuException::class, $e);
            $this->assertSame('nosuchfn', $e->getFunctionName());
            $this->assertStringContainsString("'@nosuchfn' at line 2, column 5", $e->getMessage());
        }
    }

    public function testUnknownFunctionThrowsEvenInsideABranchThatIsNotRendered(): void
    {
        $this->expectException(FunctionNotFoundException::class);
        $this->expectExceptionMessage("'@typo'");

        (new Tsuku())->process('@for(empty as e)@if(@length(@typo(e)))x@end@end', self::data());
    }

    public function testUnknownFunctionInInlineConditionThrows(): void
    {
        $this->expectException(FunctionNotFoundException::class);

        (new Tsuku())->process('@?{@nosuchfn(p) "a" : "b"}', self::data());
    }

    public function testFunctionRegisteredLaterIsKnownAtNextRender(): void
    {
        $tsuku = new Tsuku();
        $tsuku->registerFunction('isNew', fn(array $tags): bool => in_array('new', $tags, true));

        $this->assertSame('new', $tsuku->process('@if(@isNew(p.tags))new@end', self::data()));
    }

    public function testFunctionErrorInConditionFollowsTheStrictnessMode(): void
    {
        // 1.3.0 and 1.4.0 let the ArgumentCountError escape in every mode
        $template = '@if(@substr(p.sku) == "A")Y@else N@end';

        $this->assertSame(' N', (new Tsuku())->process($template, self::data()));

        $this->expectException(TsukuException::class);
        $this->expectExceptionMessage("Function 'substr' error: ");
        (new Tsuku(StrictnessMode::STRICT))->process($template, self::data());
    }
}
