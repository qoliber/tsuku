<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit\Lexer;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Lexer\SourceMap;

class SourceMapTest extends TestCase
{
    public function testSingleLine(): void
    {
        $map = new SourceMap('abc');

        $this->assertSame([1, 1], $map->position(0));
        $this->assertSame([1, 3], $map->position(2));
        $this->assertSame([1, 4], $map->position(3));
    }

    public function testLineBreaks(): void
    {
        $map = new SourceMap("ab\ncd\r\n\nx");

        $this->assertSame([1, 3], $map->position(2));
        $this->assertSame([2, 1], $map->position(3));
        $this->assertSame([2, 3], $map->position(5));
        $this->assertSame([3, 1], $map->position(7));
        $this->assertSame([4, 1], $map->position(8));
    }

    public function testContinuationsStartNewLines(): void
    {
        // "ab\<LF>cd<LF>e\<LF>f" preprocessed to "abcd\nef" with continuations at 2 and 6
        $map = new SourceMap("abcd\nef", [2, 6]);

        $this->assertSame([1, 2], $map->position(1));
        $this->assertSame([2, 1], $map->position(2));
        $this->assertSame([2, 3], $map->position(4));
        $this->assertSame([3, 1], $map->position(5));
        $this->assertSame([4, 1], $map->position(6));
    }

    public function testContinuationsAreMergedInOrder(): void
    {
        $map = new SourceMap("a\nbc", [3]);

        $this->assertSame([2, 1], $map->position(2));
        $this->assertSame([3, 1], $map->position(3));
    }

    public function testManyLines(): void
    {
        $map = new SourceMap(str_repeat("x\n", 1000));

        $this->assertSame([1, 1], $map->position(0));
        $this->assertSame([501, 2], $map->position(1001));
        $this->assertSame([1001, 1], $map->position(2000));
    }

    public function testLookupsInAnyOrder(): void
    {
        // "ab\<LF>cd<LF>e\<LF>f" preprocessed to "abcd\nef" with continuations at 2 and 6
        $map = new SourceMap("abcd\nef", [2, 6]);
        $expected = [0 => [1, 1], 1 => [1, 2], 2 => [2, 1], 4 => [2, 3], 5 => [3, 1], 6 => [4, 1], 7 => [4, 2]];

        foreach ([7, 0, 5, 4, 6, 2, 1, 1, 7, 5] as $offset) {
            $this->assertSame($expected[$offset], $map->position($offset), "offset {$offset}");
        }
    }
}
