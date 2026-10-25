<?php

/**
 * Tsuku - Transform data into any format
 *
 * @package   Qoliber\Tsuku
 * @author    Jakub Winkler <jwinkler@qoliber.com>
 * @copyright 2025 qoliber
 * @license   MIT
 */

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Characterization;

/**
 * Condition templates whose 1.3.0 results must not change.
 *
 * The expected outputs live in tests/Fixtures/v1.3.0/conditions.json and were
 * captured by running exactly these cases through the unmodified v1.3.0 code
 * (tests/Fixtures/capture-v130.php). Only single comparisons and truthiness are
 * listed here; forms that 1.3.0 evaluated wrongly on purpose of the 1.4.0 fixes
 * (and/or, in, a function call or literal used alone, operators inside string
 * literals on the left side) are covered by the 1.4.0 tests instead.
 */
final class ConditionMatrix
{
    /**
     * Values placed under the key "v" (and compared with the right-hand sides).
     *
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        return [
            'int0' => 0,
            'int5' => 5,
            'int10' => 10,
            'intNeg1' => -1,
            'float1.5' => 1.5,
            'float0' => 0.0,
            'strEmpty' => '',
            'str0' => '0',
            'str5' => '5',
            'str05' => '05',
            'str5.0' => '5.0',
            'str9' => '9',
            'str10' => '10',
            'strAbc' => 'abc',
            'strABC' => 'ABC',
            'str1e1' => '1e1',
            'strSpace5' => ' 5',
            'strNull' => 'null',
            'boolTrue' => true,
            'boolFalse' => false,
            'null' => null,
            'arrEmpty' => [],
            'arrOne' => [1],
            'arrStrings' => ['a', 'b'],
        ];
    }

    /**
     * Right-hand sides: literals and variable paths (true/false/null are
     * variable paths in 1.3.0 and stay so in 1.4.0).
     *
     * @return array<int, string>
     */
    public static function rightHandSides(): array
    {
        return [
            '0', '5', '-1', '10', '1.5', '5.0', '1e1', '.5',
            '"5"', '"05"', '""', '"0"', '"abc"', "'abc'", '"ABC"', '" 5"', '"a b"',
            'null', 'true', 'false', 'missing', 'other', 'other.deep',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function operators(): array
    {
        return ['==', '!=', '>', '<', '>=', '<='];
    }

    /**
     * Data shared by every case; "v" is set per case.
     *
     * @return array<string, mixed>
     */
    public static function baseData(): array
    {
        return [
            'other' => 5,
            'deep' => ['a' => ['b' => 'x']],
            'a.b' => 'dotted-key',
            'list' => ['first', 'second'],
            'order' => 3,
            'notes' => 'n',
            'android' => '',
            'inventory' => 7,
            'product' => ['order' => 1, 'notes' => 'abc', 'in' => 2],
            'band' => true,
            'oracle' => 0,
            'nothing' => 'x',
            'origin' => 'pl',
            'india' => 1,
            'name' => '  Abc  ',
            'user' => new CharacterizationUser(),
        ];
    }

    /**
     * All cases: id => [template, valueKey|null].
     *
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function cases(): array
    {
        $cases = [];

        foreach (array_keys(self::values()) as $valueKey) {
            foreach (self::operators() as $operator) {
                foreach (self::rightHandSides() as $rhs) {
                    $cases["if:{$valueKey}:{$operator}:{$rhs}"] = ["@if(v {$operator} {$rhs})Y@else N@end", $valueKey];
                }
                foreach (['0', '"5"', '""', 'other', 'missing'] as $lhs) {
                    $cases["lhs:{$valueKey}:{$operator}:{$lhs}"] = ["@if({$lhs} {$operator} v)Y@else N@end", $valueKey];
                    $cases["unless:{$valueKey}:{$operator}:{$lhs}"] = [
                        "@unless(v {$operator} {$lhs})Y@else N@end",
                        $valueKey,
                    ];
                    $cases["ternary:{$valueKey}:{$operator}:{$lhs}"] = [
                        "@?{v {$operator} {$lhs} \"Y\" : \"N\"}",
                        $valueKey,
                    ];
                    $cases["ternaryq:{$valueKey}:{$operator}:{$lhs}"] = [
                        "@?{v {$operator} {$lhs} ? \"Y\" : \"N\"}",
                        $valueKey,
                    ];
                }
            }

            $cases["truthy:{$valueKey}"] = ['@if(v)Y@else N@end', $valueKey];
            $cases["unlessTruthy:{$valueKey}"] = ['@unless(v)Y@else N@end', $valueKey];
            $cases["ternaryTruthy:{$valueKey}"] = ['@?{v "Y" : "N"}', $valueKey];
            $cases["ternaryTruthyQ:{$valueKey}"] = ['@?{v ? "Y" : "N"}', $valueKey];
            $cases["spacing1:{$valueKey}"] = ['@if(v>5)Y@else N@end', $valueKey];
            $cases["spacing2:{$valueKey}"] = ['@if(  v   >=   5  )Y@else N@end', $valueKey];
            $cases["spacing3:{$valueKey}"] = ["@if(\n  v == 5\n)Y@else N@end", $valueKey];
        }

        $cases['truthy:missing'] = ['@if(v)Y@else N@end', null];
        $cases['missing:eq0'] = ['@if(v == 0)Y@else N@end', null];
        $cases['missing:eqEmpty'] = ['@if(v == "")Y@else N@end', null];
        $cases['missing:lt1'] = ['@if(v < 1)Y@else N@end', null];
        $cases['missing:gtNeg1'] = ['@if(v > -1)Y@else N@end', null];
        $cases['missing:neNull'] = ['@if(v != null)Y@else N@end', null];

        $paths = [
            'deep.a.b', 'deep.a', 'deep.a.c', 'deep.x.y', 'a.b', 'list.0', 'list.1', 'list.2',
            'order', 'notes', 'android', 'inventory', 'product.order', 'product.notes', 'product.in',
            'band', 'oracle', 'nothing', 'origin', 'india', 'user.name', 'user.active', 'user.inactive',
            'user.total', 'user.nickname', 'user.secret', 'true', 'false', 'null',
        ];
        foreach ($paths as $path) {
            $cases["path:{$path}"] = ["@if({$path})Y@else N@end", null];
            $cases["pathEq:{$path}"] = ["@if({$path} == \"x\")Y@else N@end", null];
            $cases["pathGt:{$path}"] = ["@if({$path} > 0)Y@else N@end", null];
        }

        $functionCases = [
            '@length(list) > 1', '@length(list) == 2', '@length(name) == 7', '@length(missing) == 0',
            '@upper(origin) == "PL"', '@lower("ABC") == "abc"', '@trim(name) == "Abc"',
            '@substr(origin, 0, 1) == "p"', '@substr("abcdef", 2) == "cdef"', '@default(missing, "x") == "x"',
            '@default(origin, "x") == "pl"', '@first(list) == "first"', '@last(list) == "second"',
            '@join(list, "-") == "first-second"', '@upper(@trim(name)) == "ABC"', '@concat("a", "b") == "ab"',
            '@round(1.55, 1) == 1.6', '@abs(-5) == 5', '@number(1234.5, 2) == "1,234.50"',
            '@replace(origin, "p", "P") == "Pl"', '"PL" == @upper(origin)', '@length(list) >= other',
            '@capitalize(origin) != "Pl"', '@length(deep) < 2',
        ];
        foreach ($functionCases as $i => $condition) {
            $cases["function:{$i}"] = ["@if({$condition})Y@else N@end", null];
            $cases["functionTernary:{$i}"] = ["@?{{$condition} \"Y\" : \"N\"}", null];
        }

        $ternaryValues = [
            '@?{other > 0 other : "N"}', '@?{other > 0 1.50 : 0}', '@?{other < 0 1.50 : 0}',
            '@?{other > 0 @upper(origin) : @lower(origin)}', '@?{other < 0 @upper(origin) : @lower(origin)}',
            '@?{other > 0 missing : "N"}', '@?{other > 0 deep.a.b : "N"}', "@?{other > 0 'single' : 'other'}",
            '@?{other > 0 "Y" : missing}', '@?{other > 0 ? origin : "N"}', '@?{other > 0 ? 42 : 7}',
            '@?{other > 0 "with space" : "N"}', '@?{other < 0 "Y" : "with space"}',
        ];
        foreach ($ternaryValues as $i => $template) {
            $cases["ternaryValue:{$i}"] = [$template, null];
        }

        return $cases;
    }
}
