<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit\Lexer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Exception\ParseException;
use Qoliber\Tsuku\Lexer\Lexer;
use Qoliber\Tsuku\Lexer\Token;
use Qoliber\Tsuku\Lexer\TokenType;

class LexerTest extends TestCase
{
    public function testTokenizeSimpleText(): void
    {
        $lexer = new Lexer('Hello World');
        $tokens = $lexer->tokenize();

        $this->assertCount(2, $tokens); // TEXT + EOF
        $this->assertEquals(TokenType::TEXT, $tokens[0]->type);
        $this->assertEquals('Hello World', $tokens[0]->value);
    }

    public function testTokenizeVariable(): void
    {
        $lexer = new Lexer('{name}');
        $tokens = $lexer->tokenize();

        $this->assertCount(2, $tokens); // VARIABLE + EOF
        $this->assertEquals(TokenType::VARIABLE, $tokens[0]->type);
        $this->assertEquals('name', $tokens[0]->value);
    }

    public function testTokenizeForDirective(): void
    {
        $lexer = new Lexer('@for(items as item)');
        $tokens = $lexer->tokenize();

        $this->assertCount(2, $tokens); // DIRECTIVE_FOR + EOF
        $this->assertEquals(TokenType::DIRECTIVE_FOR, $tokens[0]->type);
        $this->assertEquals('items as item', $tokens[0]->value);
    }

    public function testTokenizeEndDirective(): void
    {
        $lexer = new Lexer('@end');
        $tokens = $lexer->tokenize();

        $this->assertCount(2, $tokens); // DIRECTIVE_END + EOF
        $this->assertEquals(TokenType::DIRECTIVE_END, $tokens[0]->type);
    }

    public function testTokenizeMixed(): void
    {
        $lexer = new Lexer('Hello {name}!');
        $tokens = $lexer->tokenize();

        $this->assertCount(4, $tokens); // TEXT + VARIABLE + TEXT + EOF
        $this->assertEquals(TokenType::TEXT, $tokens[0]->type);
        $this->assertEquals('Hello ', $tokens[0]->value);
        $this->assertEquals(TokenType::VARIABLE, $tokens[1]->type);
        $this->assertEquals('name', $tokens[1]->value);
        $this->assertEquals(TokenType::TEXT, $tokens[2]->type);
        $this->assertEquals('!', $tokens[2]->value);
    }

    public function testPreservesWhitespace(): void
    {
        $lexer = new Lexer("Line 1\nLine 2\n");
        $tokens = $lexer->tokenize();

        $this->assertEquals("Line 1\nLine 2\n", $tokens[0]->value);
    }

    /**
     * Compact token dump: TYPE(value) separated by spaces, EOF omitted.
     *
     * @param array<\Qoliber\Tsuku\Lexer\Token> $tokens
     * @return string
     */
    private static function dump(array $tokens): string
    {
        $parts = [];
        foreach ($tokens as $token) {
            if ($token->type !== TokenType::EOF) {
                $parts[] = $token->type->value . '(' . $token->value . ')';
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function rawTokens(): array
    {
        return [
            'escape' => ['a@@b', 'TEXT(a@b)'],
            'escape before directive name' => ['@@if(x)', 'TEXT(@if(x))'],
            'elseif' => ['@elseif(a > 1)', 'DIRECTIVE_ELSEIF(a > 1)'],
            'elseif without parentheses is 1.3.0 else + text' => ['@elseif', 'DIRECTIVE_ELSE() TEXT(if)'],
            'else prefix kept from 1.3.0' => ['@elsewhere', 'DIRECTIVE_ELSE() TEXT(where)'],
            'end prefix kept from 1.3.0' => ['@endless', 'DIRECTIVE_END() TEXT(less)'],
            'default prefix kept from 1.3.0' => ['@defaults', 'DIRECTIVE_DEFAULT() TEXT(s)'],
            'default with parenthesis is the function' => ['@default(a, "b")', 'FUNCTION(default|a, "b")'],
            'whitespace before parenthesis' => ['@if (x)y', 'DIRECTIVE_IF(x) TEXT(y)'],
            'newline before parenthesis' => ["@for\n(xs as x)", 'DIRECTIVE_FOR(xs as x)'],
            'parenthesis inside string' => ['@if(a == ")")!', 'DIRECTIVE_IF(a == ")") TEXT(!)'],
            'apostrophe is not a quote' => ["@upper(Bob's)!", "FUNCTION(upper|Bob's) TEXT(!)"],
            'nested parentheses' => ['@if((a or b) and c)', 'DIRECTIVE_IF((a or b) and c)'],
            'function' => ['@upper(name)', 'FUNCTION(upper|name)'],
            'function with empty arguments' => ['@upper()', 'FUNCTION(upper|)'],
            'ternary' => ['@?{a "x" : "y"}', 'TERNARY(a "x" : "y")'],
            'ternary with brace in string' => ['@?{a "}" : "y"}!', 'TERNARY(a "}" : "y") TEXT(!)'],
            'variable' => ['{a.b_c.0}', 'VARIABLE(a.b_c.0)'],
            'brace that is not a variable' => ['{a b}{c}', 'TEXT({a b}) VARIABLE(c)'],
            'double braces' => ['{{x}}', 'TEXT({) VARIABLE(x) TEXT(})'],
            'magento directive' => ['{{media url="a"}}', 'TEXT({{media url="a"}})'],
            'match block' => [
                '@match(v)@case(1, 2)a@default b@end',
                'DIRECTIVE_MATCH(v) DIRECTIVE_CASE(1, 2) TEXT(a) DIRECTIVE_DEFAULT() TEXT( b) DIRECTIVE_END()',
            ],
            'unless' => ['@unless(x)y@end', 'DIRECTIVE_UNLESS(x) TEXT(y) DIRECTIVE_END()'],
            'whitespace kept (raw)' => [
                "@if(x)\n  y\n@end\n",
                "DIRECTIVE_IF(x) TEXT(\n  y\n) DIRECTIVE_END() TEXT(\n)",
            ],
            'line continuation removed' => ["a\\\nb\\\r\nc", 'TEXT(abc)'],
            'escaped backslash before newline' => ["a\\\\\nb", "TEXT(a\\b)"],
        ];
    }

    #[DataProvider('rawTokens')]
    public function testRawTokens(string $template, string $expected): void
    {
        $this->assertSame($expected, self::dump((new Lexer($template))->tokenizeRaw()));
    }

    public function testCustomBlockDirectives(): void
    {
        $lexer = new Lexer('@cdata(x)a@end @cdata b@end @other(y)', ['cdata']);

        $this->assertSame(
            'DIRECTIVE_CUSTOM(cdata|x) TEXT(a) DIRECTIVE_END() TEXT( ) DIRECTIVE_CUSTOM(cdata|) TEXT( b) '
            . 'DIRECTIVE_END() TEXT( ) FUNCTION(other|y)',
            self::dump($lexer->tokenizeRaw())
        );
    }

    public function testCustomDirectiveWinsOverPrefixRules(): void
    {
        $tokens = (new Lexer('@endpoint', ['endpoint']))->tokenizeRaw();

        $this->assertSame('DIRECTIVE_CUSTOM(endpoint|)', self::dump($tokens));
    }

    public function testTokenizeAppliesWhitespaceControlAndAddsEof(): void
    {
        $tokens = (new Lexer("  @if(x)\n  y\n  @end\n"))->tokenize();

        $this->assertSame("DIRECTIVE_IF(x) TEXT(  y\n) DIRECTIVE_END()", self::dump($tokens));
        $this->assertSame(TokenType::EOF, $tokens[count($tokens) - 1]->type);
    }

    public function testPositions(): void
    {
        $tokens = (new Lexer("ab\n  @if(x > 1)y{v}"))->tokenizeRaw();

        $this->assertSame([1, 1, 0], [$tokens[0]->line, $tokens[0]->column, $tokens[0]->valueOffset]);
        $this->assertSame([2, 3, 9], [$tokens[1]->line, $tokens[1]->column, $tokens[1]->valueOffset]);
        $this->assertSame([2, 13, 15], [$tokens[2]->line, $tokens[2]->column, $tokens[2]->valueOffset]);
        $this->assertSame([2, 14, 17], [$tokens[3]->line, $tokens[3]->column, $tokens[3]->valueOffset]);
    }

    public function testPositionsAfterLineContinuation(): void
    {
        $lexer = new Lexer("a \\\n  b\n@if(x)");
        $tokens = $lexer->tokenizeRaw();

        $this->assertSame([3, 1], [$tokens[1]->line, $tokens[1]->column]);
        $this->assertSame([2, 1], $lexer->getSourceMap()->position(2));
        $this->assertSame([2, 3], $lexer->getSourceMap()->position(4));
    }

    public function testEofPosition(): void
    {
        $tokens = (new Lexer("a\nbc"))->tokenize();
        $eof = $tokens[count($tokens) - 1];

        $this->assertSame([2, 3, 4], [$eof->line, $eof->column, $eof->valueOffset]);
    }

    public function testRawTokenizationCanBeRepeated(): void
    {
        $lexer = new Lexer('a{b}');

        $this->assertSame(self::dump($lexer->tokenizeRaw()), self::dump($lexer->tokenizeRaw()));
    }

    public function testMergedTextKeepsFirstPosition(): void
    {
        $tokens = (new Lexer("x\ny@@z"))->tokenizeRaw();

        $this->assertCount(1, $tokens);
        $this->assertInstanceOf(Token::class, $tokens[0]);
        $this->assertSame([1, 1, 0], [$tokens[0]->line, $tokens[0]->column, $tokens[0]->valueOffset]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function errors(): array
    {
        return [
            'bare at' => ["x\n  a@b", 'Invalid @ syntax at line 2, column 4 (write @@ for a literal @)'],
            'at followed by a digit' => ['@1', 'Invalid @ syntax at line 1, column 1'],
            'at at end' => ['x@', 'Invalid @ syntax at line 1, column 2'],
            'directive without parenthesis' => ['@if x', 'Invalid @ syntax at line 1, column 1'],
            'function with space before parenthesis' => ['@upper (x)', 'Invalid @ syntax at line 1, column 1'],
            'default with space before parenthesis' => ['@default (x)', 'Invalid @ syntax at line 1, column 1'],
            'unclosed directive' => ["\n@if(a", 'Unclosed parenthesis after @if at line 2, column 1'],
            'unclosed function' => ['@upper(a', 'Unclosed parenthesis after @upper at line 1, column 1'],
            'unclosed ternary' => ['@?{a "x" : "y"', 'Invalid inline condition at line 1, column 1'],
            'empty ternary' => ['@?{}', 'Invalid inline condition at line 1, column 1'],
            'question mark without brace' => ['@?x', 'Invalid @ syntax at line 1, column 1'],
        ];
    }

    #[DataProvider('errors')]
    public function testErrors(string $template, string $message): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage($message);

        (new Lexer($template))->tokenize();
    }

    public function testErrorExposesPosition(): void
    {
        try {
            (new Lexer("ok\n\t@"))->tokenize();
            $this->fail('expected ParseException');
        } catch (ParseException $e) {
            $this->assertSame([2, 2], [$e->getTemplateLine(), $e->getTemplateColumn()]);
        }
    }
}
