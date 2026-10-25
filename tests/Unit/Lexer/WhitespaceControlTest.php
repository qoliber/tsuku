<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit\Lexer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Lexer\Lexer;
use Qoliber\Tsuku\Lexer\Token;
use Qoliber\Tsuku\Lexer\TokenType;
use Qoliber\Tsuku\Lexer\WhitespaceControl;

class WhitespaceControlTest extends TestCase
{
    /**
     * Directives as [NAME], text as-is, other tokens as <TYPE>.
     *
     * @param string $template
     * @param array<int, string> $directives
     * @return string
     */
    private static function layout(string $template, array $directives = []): string
    {
        $tokens = (new WhitespaceControl())->apply((new Lexer($template, $directives))->tokenizeRaw());

        $out = '';
        foreach ($tokens as $token) {
            $out .= match (true) {
                $token->type === TokenType::TEXT => $token->value,
                $token->type->isBlockDirective() => '[' . substr($token->type->value, 10) . ']',
                default => '<' . $token->type->value . '>',
            };
        }

        return $out;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function cases(): array
    {
        return [
            // Rule 1
            'layout line' => ["a\n  @if(x)  \n  b\n  @end\nc", "a\n[IF]  b\n[END]c"],
            'layout line at start of template' => ["  @if(x)\nb@end", '[IF]b[END]'],
            'layout line at end of template without newline' => ["a\n  @end  ", "a\n[END]"],
            'layout line with two directives' => ["@for(a as b)  @if(x)  \nz", '[FOR][IF]z'],
            'layout line with crlf' => ["a\r\n\t@if(x)\r\nb", "a\r\n[IF]b"],
            'variable makes it a content line' => ["  @if(x){v}\n", '  [IF]<VARIABLE>' . "\n"],
            'function makes it a content line' => ["@upper(a)@if(x)\n", '<FUNCTION>[IF]'],
            'ternary makes it a content line' => ["@?{a \"b\" : \"c\"} @end\n", "<TERNARY> [END]\n"],
            'escape is content' => ["@@ @end\n", "@ [END]\n"],
            // Rule 2
            'indentation before branch with content' => ["@if(x)\na\n  @else b\n@end", "[IF]a\n[ELSE] b\n[END]"],
            'indentation before end with content' => ["@for(a as b)\nx\n\t@end y", "[FOR]x\n[END] y"],
            'indentation before opener with content is kept' => ["a\n  @if(x)b\n@end", "a\n  [IF]b\n[END]"],
            'text before branch on its line is content' => ["@if(x)\na  @else b\n@end", "[IF]a  [ELSE] b\n[END]"],
            // Rule 3
            'opener at end of content line' => ["a @for(x as y)  \nb\n@end", 'a [FOR]b' . "\n" . '[END]'],
            'opener at end of content line, crlf' => ["a @for(x as y)\r\nb@end", 'a [FOR]b[END]'],
            'branch at end of content line' => ["@if(x)a@else\nb\n@end", "[IF]a[ELSE]b\n[END]"],
            'opener at end of input is not trimmed' => ['a @for(x as y)  ', 'a [FOR]  '],
            // Rule 4
            'end after content keeps the line break' => ["@if(x)a@end\nb", "[IF]a[END]\nb"],
            'end after content keeps trailing spaces' => ["@if(x)a@end  \nb", "[IF]a[END]  \nb"],
            // Rule 5
            'inline if trims body start' => ["a,@if(x)\n  b\n@else\n  c\n@end\n", "a,[IF]b\n[ELSE]c\n[END]"],
            'inline if keeps body start without line break' => ["a,@if(x) b@else c@end\n", "a,[IF] b[ELSE] c[END]\n"],
            'inline match' => [
                "a,@match(v)\n  @case(1)\n    b\n  @default\n    c\n@end\n",
                "a,[MATCH][CASE]b\n[DEFAULT]c\n[END]",
            ],
            'inline block closed before content trims body ends' => [
                "<x>@if(v)\n  a\n@else\n  b\n@end</x>",
                '<x>[IF]a[ELSE]b[END]</x>',
            ],
            'closed inline keeps line breaks inside the body' => [
                "<x>@if(v)\n  a\n  b\n@end</x>",
                "<x>[IF]a\n  b[END]</x>",
            ],
            'closed inline with empty body' => ['<x>@if(v)@end</x>', '<x>[IF][END]</x>'],
            'closed inline with whitespace body' => ["<x>@if(v) \n @end</x>", '<x>[IF][END]</x>'],
            'closed inline body ending in a variable' => ["<x>@if(v)\n {v}@end</x>", '<x>[IF]<VARIABLE>[END]</x>'],
            'closed inline with spaces only at the end' => ["<x>@if(v)a  @end</x>", '<x>[IF]a  [END]</x>'],
            'inline for is not trimmed' => ["a,@for(x as y)\n  b\n@end</x>", "a,[FOR]  b\n[END]</x>"],
            'inline custom directive' => ["a,@wrap\n  b\n@end\n", "a,[CUSTOM]b\n[END]"],
            'standalone block is not trimmed' => ["@if(v)\n  a\n@end\n", "[IF]  a\n[END]"],
            'nested inline blocks' => ["a,@if(x)\n  b,@if(y)\n    c\n  @end\n@end\n", "a,[IF]b,[IF]c\n[END][END]"],
            'block after an inline block on the same stack level' => [
                "a,@if(x)\n b\n@end\n@if(y)\n  c\n@end\n",
                "a,[IF]b\n[END][IF]  c\n[END]",
            ],
            'unbalanced end is left for the parser' => ["@end\n  @else\nx", '[END][ELSE]x'],
            'unclosed block is only trimmed by the line rules' => ["a,@if(x)\n  b", 'a,[IF]  b'],
        ];
    }

    #[DataProvider('cases')]
    public function testLayout(string $template, string $expected): void
    {
        $this->assertSame($expected, self::layout($template, ['wrap']));
    }

    public function testTokensWithoutChangesAreReturnedAsIs(): void
    {
        $tokens = (new Lexer('a{b}c'))->tokenizeRaw();
        $result = (new WhitespaceControl())->apply($tokens);

        $this->assertSame($tokens, $result);
    }

    public function testTrimmedTextKeepsItsPosition(): void
    {
        $tokens = (new WhitespaceControl())->apply((new Lexer("x\n  @if(a)\nb@end"))->tokenizeRaw());

        $this->assertSame("x\n", $tokens[0]->value);
        $this->assertSame([1, 1, 0], [$tokens[0]->line, $tokens[0]->column, $tokens[0]->valueOffset]);
        // A token trimmed at the start still reports where the original text began
        $this->assertSame('b', $tokens[2]->value);
        $this->assertSame([2, 9], [$tokens[2]->line, $tokens[2]->column]);
    }

    public function testTokensWithArbitraryKeys(): void
    {
        $tokens = (new WhitespaceControl())->apply([
            7 => new Token(TokenType::TEXT, "a\n  ", 1, 1),
            3 => new Token(TokenType::DIRECTIVE_IF, 'x', 2, 3),
            9 => new Token(TokenType::TEXT, "\nb", 2, 9),
        ]);

        $this->assertSame(["a\n", 'x', 'b'], array_map(fn(Token $token): string => $token->value, $tokens));
    }

    public function testEmptyInput(): void
    {
        $this->assertSame([], (new WhitespaceControl())->apply([]));
    }

    public function testInstanceCanBeReused(): void
    {
        $control = new WhitespaceControl();
        $control->apply((new Lexer("  @if(a)\n"))->tokenizeRaw());
        $tokens = $control->apply([new Token(TokenType::TEXT, "  x\n", 1, 1)]);

        $this->assertSame("  x\n", $tokens[0]->value);
    }
}
