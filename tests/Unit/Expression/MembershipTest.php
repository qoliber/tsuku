<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit\Expression;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Expression\Membership;

class MembershipTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: mixed, 2: bool}>
     */
    public static function cases(): array
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return '7231';
            }
        };

        return [
            'string in list of ints' => [[7231, 12], '7231', true],
            'int in list of strings' => [['7231', '12'], 7231, true],
            'float compares as string' => [['1.5'], 1.5, true],
            'no partial match in list' => [['7231'], '723', false],
            'list is not trimmed' => [[' 12'], '12', false],
            'list values, not keys' => [['a' => 'x'], 'a', false],
            'associative list value' => [['a' => 'x'], 'x', true],
            'true is "1"' => [['1'], true, true],
            'false is ""' => [[''], false, true],
            'null needle' => [[null, ''], null, false],
            'array needle' => [[['a']], ['a'], false],
            'null items are skipped' => [[null], '', false],
            'nested arrays are skipped' => [[['7231']], '7231', false],
            'stringable needle' => [[7231], $stringable, true],
            'stringable item' => [[$stringable], '7231', true],
            'plain object item' => [[new \stdClass()], '1', false],
            'empty list' => [[], 'x', false],
            'substring' => ['7231,12', '31,1', true],
            'substring is case-sensitive' => ['Sale', 'sale', false],
            'empty needle is in every string' => ['abc', '', true],
            'int needle in string' => ['a12b', 12, true],
            'int haystack' => [7231, '7', false],
            'null haystack' => [null, 'x', false],
            'object haystack' => [$stringable, '7', false],
            'traversable haystack' => [new \ArrayIterator(['x']), 'x', false],
        ];
    }

    #[DataProvider('cases')]
    public function testContains(mixed $haystack, mixed $needle, bool $expected): void
    {
        $this->assertSame($expected, Membership::contains($haystack, $needle));
    }
}
