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

/**
 * Renders every examples/*.php script and compares it with the output captured
 * from v1.3.0 (tests/Fixtures/v1.3.0/examples). Where 1.4.0 intentionally
 * renders differently, the expected output lives in tests/Fixtures/v1.4.0/examples
 * and every such difference is listed in CHANGELOG.md.
 */
final class ExampleCorpusTest extends TestCase
{
    private const V130 = __DIR__ . '/../Fixtures/v1.3.0/examples/';
    private const V140 = __DIR__ . '/../Fixtures/v1.4.0/examples/';

    /**
     * @return array<string, array{0: string}>
     */
    public static function examples(): array
    {
        $examples = [];
        foreach (glob(__DIR__ . '/../../examples/*.php') ?: [] as $script) {
            $examples[basename($script, '.php')] = [(string) realpath($script)];
        }

        return $examples;
    }

    #[DataProvider('examples')]
    public function testExampleRendersAsCaptured(string $script): void
    {
        $name = basename($script, '.php');
        $directory = is_file(self::V140 . $name . '.txt') ? self::V140 : self::V130;

        $this->assertFileExists($directory . $name . '.txt', 'every example needs a captured output');

        $result = ExampleRunner::run($script);

        $this->assertSame(trim((string) file_get_contents($directory . $name . '.status')), $result['status']);
        $this->assertSame((string) file_get_contents($directory . $name . '.txt'), $result['stdout']);
    }

    public function testEveryOverrideIsARealDifference(): void
    {
        $overrides = glob(self::V140 . '*.txt') ?: [];
        foreach ($overrides as $override) {
            $baseline = self::V130 . basename($override);
            if (!is_file($baseline)) {
                continue;
            }
            $this->assertNotSame(
                file_get_contents($baseline),
                file_get_contents($override),
                basename($override) . ' is identical to the 1.3.0 capture; delete the override'
            );
        }
        $this->assertGreaterThanOrEqual(0, count($overrides));
    }
}
