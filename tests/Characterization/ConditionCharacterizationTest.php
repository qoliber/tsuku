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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

/**
 * Every single comparison and truthiness check renders exactly as in 1.3.0:
 * loose PHP comparison, numeric strings, missing variables, true/false/null
 * resolved as variable paths, function operands, ternary values.
 */
final class ConditionCharacterizationTest extends TestCase
{
    /** @var array<string, string>|null */
    private static ?array $expected = null;

    /**
     * @return array<string, string>
     */
    private static function expected(): array
    {
        if (self::$expected === null) {
            $json = (string) file_get_contents(__DIR__ . '/../Fixtures/v1.3.0/conditions.json');
            /** @var array<string, string> $decoded */
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            self::$expected = $decoded;
        }

        return self::$expected;
    }

    public function testFixtureCoversEveryCase(): void
    {
        $this->assertSame(array_keys(ConditionMatrix::cases()), array_keys(self::expected()));
    }

    /**
     * One test per group keeps the report readable; every case is still asserted.
     *
     * @return array<string, array{0: string}>
     */
    public static function caseGroups(): array
    {
        $groups = [];
        foreach (array_keys(ConditionMatrix::cases()) as $id) {
            $group = explode(':', $id, 2)[0];
            $groups[$group] = [$group];
        }

        return $groups;
    }

    #[DataProvider('caseGroups')]
    public function testGroupRendersAsInV130(string $group): void
    {
        $expected = self::expected();
        $actual = [];
        $wanted = [];

        foreach (ConditionMatrix::cases() as $id => [$template, $valueKey]) {
            if (explode(':', $id, 2)[0] !== $group) {
                continue;
            }
            $data = ConditionMatrix::baseData();
            if ($valueKey !== null) {
                $data['v'] = ConditionMatrix::values()[$valueKey];
            }
            try {
                $actual[$id] = (new Tsuku())->process($template, $data);
            } catch (\Throwable $e) {
                $actual[$id] = '!' . get_class($e);
            }
            $wanted[$id] = $expected[$id];
        }

        $this->assertNotEmpty($wanted);
        $this->assertSame($wanted, $actual);
    }
}
