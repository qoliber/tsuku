<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Support\Syntax;

class SyntaxTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: int, 2: int|null}>
     */
    public static function parentheses(): array
    {
        return [
            'simple' => ['f(a)', 1, 3],
            'nested' => ['f(a(b)c)d', 1, 7],
            'parenthesis in double quotes' => ['f(")")', 1, 5],
            'parenthesis in single quotes' => ["f('(')", 1, 5],
            'escaped quote inside string' => ['f("\\")")', 1, 7],
            'other quote inside string' => ['f("\')")x', 1, 6],
            'apostrophe after a letter' => ["f(it's)", 1, 6],
            'apostrophe after a digit' => ["f(5')", 1, 4],
            'apostrophe after underscore' => ["f(a_')", 1, 5],
            'quote after a space opens a string' => ["f(a ')' )", 1, 8],
            'escaped quote does not open a string' => ['f(\\")', 1, 4],
            'unterminated quote falls back to 1.3.0 scan' => ['f(")', 1, 3],
            'unbalanced' => ['f((a)', 1, null],
            'starting mid-text' => ['x(a) y(b)', 6, 8],
        ];
    }

    #[DataProvider('parentheses')]
    public function testFindClosingParenthesis(string $text, int $open, ?int $expected): void
    {
        $this->assertSame($expected, Syntax::findClosingParenthesis($text, $open));
    }

    /**
     * @return array<string, array{0: string, 1: int|null}>
     */
    public static function braces(): array
    {
        return [
            'simple' => ['a}', 1],
            'brace in string' => ['a "}" b}', 7],
            'brace in single quotes' => ["'}'}", 3],
            'escaped quote' => ['"\\"}"}', 5],
            'apostrophe' => ["it's}", 4],
            'none' => ['abc', null],
            'unterminated string' => ['"}', null],
        ];
    }

    #[DataProvider('braces')]
    public function testFindClosingBrace(string $text, ?int $expected): void
    {
        $this->assertSame($expected, Syntax::findClosingBrace($text, 0));
    }

    public function testFindClosingBraceFromOffset(): void
    {
        $this->assertSame(3, Syntax::findClosingBrace('}ab}}', 1));
    }

    /**
     * @return array<string, array{0: string, 1: array<int, string>}>
     */
    public static function arguments(): array
    {
        return [
            'single' => ['a', ['a']],
            'trimmed' => [' a ,  b ', ['a', 'b']],
            'comma in double quotes' => ['"a,b", c', ['"a,b"', 'c']],
            'comma in single quotes' => ["'a,b', c", ["'a,b'", 'c']],
            'comma in nested call' => ['@f(a, b), c', ['@f(a, b)', 'c']],
            'parenthesis in string inside call' => ['@f(")"), c', ['@f(")")', 'c']],
            'escaped quote' => ['"a\\",b", c', ['"a\\",b"', 'c']],
            'apostrophe' => ["Bob's, x", ["Bob's", 'x']],
            'trailing comma is dropped' => ['a,', ['a']],
            'leading comma gives an empty argument' => [',a', ['', 'a']],
            'empty' => ['', []],
        ];
    }

    /**
     * @param array<int, string> $expected
     */
    #[DataProvider('arguments')]
    public function testSplitArguments(string $arguments, array $expected): void
    {
        $this->assertSame($expected, Syntax::splitArguments($arguments));
    }

    public function testOpensString(): void
    {
        $this->assertTrue(Syntax::opensString('"a"', 0));
        $this->assertTrue(Syntax::opensString('("a")', 1));
        $this->assertTrue(Syntax::opensString("x '", 2));
        $this->assertFalse(Syntax::opensString("x'", 1));
        $this->assertFalse(Syntax::opensString('Z"', 1));
        $this->assertFalse(Syntax::opensString('9"', 1));
        $this->assertFalse(Syntax::opensString('\\"', 1));
        $this->assertFalse(Syntax::opensString('a', 0));
        $this->assertTrue(Syntax::opensString('@"', 1));
        $this->assertTrue(Syntax::opensString('{"', 1));

        // Boundaries of [0-9A-Za-z]: the characters around them do open strings
        foreach (['0', '9', 'A', 'Z', 'a', 'z', '_'] as $wordCharacter) {
            $this->assertFalse(Syntax::opensString($wordCharacter . '"', 1), $wordCharacter);
        }
        foreach (['/', ':', '@', '[', '`', '{', '^'] as $otherCharacter) {
            $this->assertTrue(Syntax::opensString($otherCharacter . '"', 1), $otherCharacter);
        }
    }
}
