<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit\Lexer;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Lexer\Lexer;
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
}
