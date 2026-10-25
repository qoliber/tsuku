<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit\Ast;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Ast\CaseNode;
use Qoliber\Tsuku\Ast\DirectiveNode;
use Qoliber\Tsuku\Ast\ForNode;
use Qoliber\Tsuku\Ast\FunctionNode;
use Qoliber\Tsuku\Ast\IfNode;
use Qoliber\Tsuku\Ast\MatchNode;
use Qoliber\Tsuku\Ast\Parser;
use Qoliber\Tsuku\Ast\TemplateNode;
use Qoliber\Tsuku\Ast\TernaryNode;
use Qoliber\Tsuku\Ast\TextNode;
use Qoliber\Tsuku\Ast\VariableNode;
use Qoliber\Tsuku\Exception\FunctionNotFoundException;
use Qoliber\Tsuku\Exception\ParseException;
use Qoliber\Tsuku\Expression\ComparisonExpression;
use Qoliber\Tsuku\Expression\PathExpression;
use Qoliber\Tsuku\Function\FunctionRegistry;
use Qoliber\Tsuku\Lexer\Lexer;
use Qoliber\Tsuku\Lexer\Token;
use Qoliber\Tsuku\Lexer\TokenType;

class ParserTest extends TestCase
{
    /**
     * @param string $template
     * @param array<int, string> $directives
     * @return \Qoliber\Tsuku\Ast\TemplateNode
     */
    private static function parse(
        string $template,
        array $directives = [],
        ?FunctionRegistry $functions = null
    ): TemplateNode {
        $lexer = new Lexer($template, $directives);

        return (new Parser($lexer->tokenize(), $lexer->getSourceMap(), $functions))->parse();
    }

    public function testTextVariableAndFunction(): void
    {
        $children = self::parse('Hi {user.name} @upper(a, "b, c")')->children;

        $this->assertInstanceOf(TextNode::class, $children[0]);
        $this->assertSame('Hi ', $children[0]->content);
        $this->assertInstanceOf(VariableNode::class, $children[1]);
        $this->assertSame('user.name', $children[1]->name);
        $this->assertInstanceOf(FunctionNode::class, $children[3]);
        $this->assertSame('upper', $children[3]->name);
        $this->assertSame(['a', '"b, c"'], $children[3]->arguments);
    }

    public function testFunctionWithoutArguments(): void
    {
        $function = self::parse('@now()')->children[0];

        $this->assertInstanceOf(FunctionNode::class, $function);
        $this->assertSame([], $function->arguments);
    }

    public function testForWithKey(): void
    {
        $for = self::parse('@for(a.list as item, key){key}@end')->children[0];

        $this->assertInstanceOf(ForNode::class, $for);
        $this->assertSame(['a.list', 'item', 'key'], [$for->collection, $for->itemVar, $for->keyVar]);
        $this->assertCount(1, $for->children);
    }

    public function testIfCarriesParsedCondition(): void
    {
        $if = self::parse('@if( a > 1 )x@else y@end')->children[0];

        $this->assertInstanceOf(IfNode::class, $if);
        $this->assertSame('a > 1', $if->condition);
        $this->assertFalse($if->negate);
        $this->assertInstanceOf(ComparisonExpression::class, $if->expression);
        $this->assertCount(1, $if->children);
        $this->assertCount(1, $if->elseChildren);
    }

    public function testUnless(): void
    {
        $unless = self::parse('@unless(a)x@end')->children[0];

        $this->assertInstanceOf(IfNode::class, $unless);
        $this->assertTrue($unless->negate);
        $this->assertSame([], $unless->elseChildren);
    }

    public function testElseifBecomesNestedIf(): void
    {
        $if = self::parse('@unless(a)A@elseif(b)B@elseif(c)C@else D@end!')->children;

        $this->assertCount(2, $if);
        $outer = $if[0];
        $this->assertInstanceOf(IfNode::class, $outer);
        $this->assertTrue($outer->negate);
        $second = $outer->elseChildren[0];
        $this->assertInstanceOf(IfNode::class, $second);
        $this->assertSame('b', $second->condition);
        $this->assertFalse($second->negate);
        $third = $second->elseChildren[0];
        $this->assertInstanceOf(IfNode::class, $third);
        $this->assertSame('c', $third->condition);
        $this->assertInstanceOf(PathExpression::class, $third->expression);
        $this->assertCount(1, $third->elseChildren);
        $this->assertInstanceOf(TextNode::class, $if[1]);
    }

    public function testMatch(): void
    {
        $match = self::parse("@match( status )\n  @case(\"a\", 'b')A\n  @case(1)B\n  @default\n  C\n@end")->children[0];

        $this->assertInstanceOf(MatchNode::class, $match);
        $this->assertSame('status', $match->expression);
        $this->assertCount(2, $match->cases);
        $this->assertInstanceOf(CaseNode::class, $match->cases[0]);
        $this->assertSame(['"a"', "'b'"], $match->cases[0]->values);
        $this->assertSame(['1'], $match->cases[1]->values);
        $this->assertCount(1, $match->defaultChildren);
    }

    public function testMatchSkipsWhitespaceBeforeFirstCase(): void
    {
        $match = self::parse('x@match(v)   @case(1)a@end')->children[1];

        $this->assertInstanceOf(MatchNode::class, $match);
        $this->assertCount(1, $match->cases);
    }

    public function testTernary(): void
    {
        $ternary = self::parse('@?{a and b "x" : @upper(c)}')->children[0];

        $this->assertInstanceOf(TernaryNode::class, $ternary);
        $this->assertSame('a and b', $ternary->condition);
        $this->assertSame(['"x"', '@upper(c)'], [$ternary->trueValue, $ternary->falseValue]);
        $this->assertNotNull($ternary->expression);
    }

    public function testCustomDirective(): void
    {
        $directive = self::parse('@wrap("<", b)x{y}@end @wrap z@end', ['wrap'])->children;

        $this->assertInstanceOf(DirectiveNode::class, $directive[0]);
        $this->assertSame('wrap', $directive[0]->name);
        $this->assertSame(['"<"', 'b'], $directive[0]->arguments);
        $this->assertCount(2, $directive[0]->children);
        $this->assertInstanceOf(DirectiveNode::class, $directive[2]);
        $this->assertSame([], $directive[2]->arguments);
    }

    public function testParserWithoutSourceMapUsesTokenPositions(): void
    {
        $tokens = [
            new Token(TokenType::DIRECTIVE_IF, 'a b', 4, 10),
            new Token(TokenType::DIRECTIVE_END, '', 4, 20),
            new Token(TokenType::EOF, '', 4, 24),
        ];

        $this->expectExceptionMessage("Unexpected 'b' at line 4, column 12");
        (new Parser($tokens))->parse();
    }

    public function testSourceMapIsUsedFromTheFirstOffset(): void
    {
        $tokens = [
            new Token(TokenType::DIRECTIVE_IF, 'a b', 5, 1, 0),
            new Token(TokenType::DIRECTIVE_END, '', 5, 9),
            new Token(TokenType::EOF, '', 5, 13),
        ];

        $this->expectExceptionMessage("Unexpected 'b' at line 1, column 3");
        (new Parser($tokens, new \Qoliber\Tsuku\Lexer\SourceMap('a b')))->parse();
    }

    public function testUnknownFunctionWithRegistry(): void
    {
        $this->expectException(FunctionNotFoundException::class);
        $this->expectExceptionMessage("'@nope' at line 2, column 5");

        self::parse("x\n@if(@nope(a))y@end", [], new FunctionRegistry());
    }

    public function testUnknownFunctionWithoutRegistryParses(): void
    {
        $this->assertCount(1, self::parse('@if(@nope(a))y@end')->children);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function errors(): array
    {
        return [
            'else outside' => ["\n  @else", 'Unexpected @else at line 2, column 3 - @else must follow @if or @unless'],
            'elseif outside' => [
                '@elseif(a)',
                'Unexpected @elseif at line 1, column 1 - @elseif must follow @if or @unless',
            ],
            'case outside' => ['x@case(1)', 'Unexpected @case at line 1, column 2 - @case must be inside @match'],
            'default outside' => [
                '@default',
                'Unexpected @default at line 1, column 1 - @default must be inside @match',
            ],
            'end outside' => ['ab@end', 'Unexpected @end at line 1, column 3'],
            'unclosed if' => [
                "@if(a)\nx",
                'Expected @else or @end but reached end of file at line 2, column 2 (@if opened at line 1, column 1)',
            ],
            'unclosed unless else' => [
                '@unless(a)x@else y',
                'Expected @end but reached end of file at line 1, column 19 (@unless opened at line 1, column 1)',
            ],
            'unclosed elseif chain' => ['@if(a)x@elseif(b)y', '(@if opened at line 1, column 8)'],
            'unclosed for' => [
                '@for(a as b)x',
                'Expected @end but reached end of file at line 1, column 14 (@for opened at line 1, column 1)',
            ],
            'invalid for' => [
                '@for(a)x@end',
                "Invalid @for syntax at line 1, column 1: 'a'. Expected: 'items as item' or 'items as item, key'",
            ],
            'two else' => ['@if(a)x@else y@else z@end', 'Unexpected @else at line 1, column 15'],
            'unclosed match' => [
                '@match(a)@case(1)x',
                'Expected @end but reached end of file at line 1, column 19 (@match opened at line 1, column 1)',
            ],
            'case after default' => [
                '@match(a)@default x@case(1)y@end',
                'Expected @end but found @case at line 1, column 20 (@match opened at line 1, column 1)',
            ],
            'two defaults' => ['@match(a)@default x@default y@end', 'Expected @end but found @default'],
            'text before first case' => [
                "@match(a)\n  x\n@case(1)y@end",
                'Expected @case or @default inside @match at line 1, column 10',
            ],
            'else inside case' => ['@match(a)@case(1)x@else y@end', 'Unexpected @else'],
            'unclosed custom directive' => [
                '@wrap()x',
                'Expected @end but reached end of file at line 1, column 9 (@wrap opened at line 1, column 1)',
            ],
            'malformed condition' => ['@if(a ==)x@end', 'Missing value at line 1, column 9'],
            'malformed inline condition' => [
                '@?{a : b}',
                'Expected the value for a true condition at line 1, column 6',
            ],
        ];
    }

    #[DataProvider('errors')]
    public function testErrors(string $template, string $message): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage($message);

        self::parse($template, ['wrap']);
    }

    public function testErrorPositionIsExposed(): void
    {
        try {
            self::parse("a\n b@end");
            $this->fail('expected ParseException');
        } catch (ParseException $e) {
            $this->assertSame([2, 3], [$e->getTemplateLine(), $e->getTemplateColumn()]);
        }
    }

    public function testEofTokenAloneIsAnEmptyTemplate(): void
    {
        $this->assertSame([], (new Parser([new Token(TokenType::EOF, '', 1, 1)]))->parse()->children);
    }
}
