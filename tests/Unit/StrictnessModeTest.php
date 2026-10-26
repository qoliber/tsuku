<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\ProcessingContext;
use Qoliber\Tsuku\Tsuku;
use Qoliber\Tsuku\StrictnessMode;
use Qoliber\Tsuku\Exception\TsukuException;

class StrictnessModeTest extends TestCase
{
    public function testSilentModeWithMissingVariable(): void
    {
        $tsuku = new Tsuku(StrictnessMode::SILENT);

        $template = 'Hello {name}, your email is {email}';
        $data = ['name' => 'John'];

        $result = $tsuku->process($template, $data);

        // In SILENT mode, missing variables return empty string
        $this->assertEquals('Hello John, your email is ', $result);
        $this->assertFalse($tsuku->hasWarnings());
        $this->assertEmpty($tsuku->getWarnings());
    }

    public function testSilentModeWithMissingFunction(): void
    {
        $tsuku = new Tsuku(StrictnessMode::SILENT);

        $template = 'Result: @nonexistent(value)';
        $data = ['value' => 'test'];

        $result = $tsuku->process($template, $data);

        // In SILENT mode, missing functions return empty string
        $this->assertEquals('Result: ', $result);
        $this->assertFalse($tsuku->hasWarnings());
    }

    public function testWarningModeWithMissingVariable(): void
    {
        $tsuku = new Tsuku(StrictnessMode::WARNING);

        $template = 'Hello {name}, your email is {email}';
        $data = ['name' => 'John'];

        $result = $tsuku->process($template, $data);

        // In WARNING mode, missing variables return empty string but log warnings
        $this->assertEquals('Hello John, your email is ', $result);
        $this->assertTrue($tsuku->hasWarnings());

        $warnings = $tsuku->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('email', $warnings[0]);
        $this->assertStringContainsString('not found', $warnings[0]);
    }

    public function testWarningModeWithMultipleMissingVariables(): void
    {
        $tsuku = new Tsuku(StrictnessMode::WARNING);

        $template = '{var1} {var2} {var3}';
        $data = ['var2' => 'Found'];

        $result = $tsuku->process($template, $data);

        $this->assertEquals(' Found ', $result);
        $this->assertTrue($tsuku->hasWarnings());

        $warnings = $tsuku->getWarnings();
        $this->assertCount(2, $warnings);
    }

    public function testWarningModeWithMissingFunction(): void
    {
        $tsuku = new Tsuku(StrictnessMode::WARNING);

        $template = 'Result: @nonexistent(value)';
        $data = ['value' => 'test'];

        $result = $tsuku->process($template, $data);

        $this->assertEquals('Result: ', $result);
        $this->assertTrue($tsuku->hasWarnings());

        $warnings = $tsuku->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('nonexistent', $warnings[0]);
        $this->assertStringContainsString('not found', $warnings[0]);
    }

    public function testWarningModeClearsWarningsBetweenProcessCalls(): void
    {
        $tsuku = new Tsuku(StrictnessMode::WARNING);

        // First process with missing variable
        $tsuku->process('{missing}', []);
        $this->assertTrue($tsuku->hasWarnings());

        // Second process without missing variables
        $result = $tsuku->process('{found}', ['found' => 'value']);
        $this->assertEquals('value', $result);
        $this->assertFalse($tsuku->hasWarnings());
    }

    public function testStrictModeThrowsExceptionForMissingVariable(): void
    {
        $tsuku = new Tsuku(StrictnessMode::STRICT);

        $template = 'Hello {name}, your email is {email}';
        $data = ['name' => 'John'];

        $this->expectException(TsukuException::class);
        $this->expectExceptionMessage("Variable 'email' not found");

        $tsuku->process($template, $data);
    }

    public function testStrictModeThrowsExceptionForMissingFunction(): void
    {
        $tsuku = new Tsuku(StrictnessMode::STRICT);

        $template = 'Result: @nonexistent(value)';
        $data = ['value' => 'test'];

        $this->expectException(TsukuException::class);
        $this->expectExceptionMessage("Function 'nonexistent' not found");

        $tsuku->process($template, $data);
    }

    public function testStrictModeSucceedsWithAllVariablesPresent(): void
    {
        $tsuku = new Tsuku(StrictnessMode::STRICT);

        $template = 'Hello {name}, your email is {email}';
        $data = ['name' => 'John', 'email' => 'john@example.com'];

        $result = $tsuku->process($template, $data);

        $this->assertEquals('Hello John, your email is john@example.com', $result);
        $this->assertFalse($tsuku->hasWarnings());
    }

    public function testOverrideStrictnessModePerProcess(): void
    {
        // Create with SILENT mode
        $tsuku = new Tsuku(StrictnessMode::SILENT);

        // Override to STRICT for this specific process
        $this->expectException(TsukuException::class);
        $tsuku->process('{missing}', [], StrictnessMode::STRICT);
    }

    public function testStrictModeWithNestedVariables(): void
    {
        $tsuku = new Tsuku(StrictnessMode::STRICT);

        $template = '{user.name} - {user.email}';
        $data = [
            'user' => [
                'name' => 'John',
                // Missing 'email'
            ],
        ];

        $this->expectException(TsukuException::class);
        $this->expectExceptionMessage("Variable 'user.email' not found");

        $tsuku->process($template, $data);
    }

    public function testWarningModeWithFunctionInLoop(): void
    {
        $tsuku = new Tsuku(StrictnessMode::WARNING);

        $template = '@for(items as item)
@nonexistent(item)
@end';
        $data = ['items' => ['a', 'b', 'c']];

        $result = $tsuku->process($template, $data);

        // Should have warnings for each loop iteration
        $this->assertTrue($tsuku->hasWarnings());
        $warnings = $tsuku->getWarnings();
        $this->assertCount(3, $warnings);
    }

    public function testStrictModeWithConditional(): void
    {
        $tsuku = new Tsuku(StrictnessMode::STRICT);

        $template = '@if(showDetails)
Name: {name}
Email: {email}
@end';

        $data = [
            'showDetails' => true,
            'name' => 'John',
            'email' => 'john@example.com',
        ];

        $result = $tsuku->process($template, $data);

        $this->assertStringContainsString('John', $result);
        $this->assertStringContainsString('john@example.com', $result);
    }

    public function testStrictModeFailsInsideConditional(): void
    {
        $tsuku = new Tsuku(StrictnessMode::STRICT);

        $template = '@if(showDetails)
Name: {name}
Email: {missing}
@end';

        $data = [
            'showDetails' => true,
            'name' => 'John',
        ];

        $this->expectException(TsukuException::class);
        $this->expectExceptionMessage("Variable 'missing' not found");

        $tsuku->process($template, $data);
    }

    public function testSilentModeWithComplexTemplate(): void
    {
        $tsuku = new Tsuku(StrictnessMode::SILENT);

        $template = 'User: {user.name}
Email: {user.email}
Status: @?{user.active "Active" : "Inactive"}
Tags: @join(user.tags, ", ")
@for(user.orders as order)
  Order: {order.id} - {order.status}
@end';

        $data = [
            'user' => [
                'name' => 'John',
                // Missing email
                'active' => 1,
                'tags' => ['vip', 'premium'],
                'orders' => [
                    ['id' => 1, 'status' => 'completed'],
                    ['id' => 2], // Missing status
                ],
            ],
        ];

        // Should not throw, missing values return empty
        $result = $tsuku->process($template, $data);

        $this->assertStringContainsString('John', $result);
        $this->assertStringContainsString('vip, premium', $result);
        $this->assertFalse($tsuku->hasWarnings());
    }

    public function testWarningModeCollectsAllWarnings(): void
    {
        $tsuku = new Tsuku(StrictnessMode::WARNING);

        $template = '{missing1} @nonexistent(value) {missing2}';
        $data = ['value' => 'test'];

        $result = $tsuku->process($template, $data);

        $warnings = $tsuku->getWarnings();
        $this->assertCount(3, $warnings);

        // Check that all warnings are collected
        $warningText = implode(' ', $warnings);
        $this->assertStringContainsString('missing1', $warningText);
        $this->assertStringContainsString('nonexistent', $warningText);
        $this->assertStringContainsString('missing2', $warningText);
    }

    public function testWarningsOfAPerCallModeAreReadable(): void
    {
        $tsuku = new Tsuku(StrictnessMode::SILENT);

        $tsuku->process('{missing}', [], StrictnessMode::WARNING);

        $this->assertTrue($tsuku->hasWarnings());
        $this->assertSame(["Variable 'missing' not found in data"], $tsuku->getWarnings());

        // The next call starts over, in the instance's own mode
        $tsuku->process('{missing}', []);
        $this->assertFalse($tsuku->hasWarnings());
        $this->assertSame([], $tsuku->getWarnings());
    }

    public function testWarningsOfAPerCallModeDoNotLeakIntoTheInstanceMode(): void
    {
        $tsuku = new Tsuku(StrictnessMode::WARNING);

        $tsuku->process('{first}', [], StrictnessMode::WARNING);
        $tsuku->process('{second}', []);

        $this->assertSame(["Variable 'second' not found in data"], $tsuku->getWarnings());
    }

    /**
     * Streaming keeps memory bounded in WARNING mode too: a warning on every row
     * keeps only the first WARNING_LIMIT messages, and counts them all
     */
    public function testStreamingKeepsAtMostTheWarningLimitAndCountsEveryWarning(): void
    {
        $tsuku = new Tsuku(StrictnessMode::WARNING);
        $rows = (static function (): \Generator {
            for ($i = 1; $i <= 1500; $i++) {
                yield ['sku' => "SKU-{$i}"];
            }
        })();

        $tsuku->processToStream(
            '@for(rows as row){row.sku}{row.missing}@end',
            [],
            $rows,
            'rows',
            static function (string $chunk): void {
            }
        );

        $this->assertCount(ProcessingContext::WARNING_LIMIT, $tsuku->getWarnings());
        $this->assertSame(1500, $tsuku->getWarningCount());

        // The next call starts the count over
        $tsuku->process('{other}', []);
        $this->assertSame(["Variable 'other' not found in data"], $tsuku->getWarnings());
        $this->assertSame(1, $tsuku->getWarningCount());
    }

    public function testWarningCountOfAPerCallMode(): void
    {
        $tsuku = new Tsuku(StrictnessMode::SILENT);

        $tsuku->process('{a}{b}{a}', [], StrictnessMode::WARNING);
        $this->assertSame(3, $tsuku->getWarningCount());

        $tsuku->process('{a}', []);
        $this->assertSame(0, $tsuku->getWarningCount());
    }

    public function testWarningsOfAPerCallModeWhileStreamingAreReadable(): void
    {
        $tsuku = new Tsuku(StrictnessMode::SILENT);

        $tsuku->processToStream(
            '@for(rows as row){row.missing}@end',
            [],
            [['sku' => 'A']],
            'rows',
            static function (string $chunk): void {
            },
            StrictnessMode::WARNING
        );

        $this->assertSame(["Variable 'row.missing' not found in data"], $tsuku->getWarnings());
    }

    /**
     * Template, output, and the function the error names (in a ternary value, as in the output,
     * the outermost call; in a condition, the call that threw)
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function failingFunctionInConditionProvider(): array
    {
        return [
            '@if' => ['@if(@boom(x))yes@else no@end', ' no', 'boom'],
            '@if not' => ['@if(not @boom(x))yes@end', 'yes', 'boom'],
            '@unless' => ['@unless(@boom(x))yes@end', 'yes', 'boom'],
            '@elseif' => ['@if(x == 2)two@elseif(@boom(x))boom@else other@end', ' other', 'boom'],
            'and' => ['@if(x == 1 and @boom(x))yes@else no@end', ' no', 'boom'],
            'comparison' => ['@if(@boom(x) == "")empty@end', 'empty', 'boom'],
            'nested in a condition' => ['@if(@upper(@boom(x)) == "")empty@end', 'empty', 'boom'],
            'ternary condition' => ['@?{@boom(x) "yes" : "no"}', 'no', 'boom'],
            'ternary value' => ['[@?{x == 1 @boom(x) : "no"}]', '[]', 'boom'],
            'nested in a ternary value' => ['[@?{x == 1 @upper(@boom(x)) : "no"}]', '[]', 'upper'],
        ];
    }

    #[DataProvider('failingFunctionInConditionProvider')]
    public function testSilentModeTreatsAFailingFunctionInAConditionAsEmpty(string $template, string $expected): void
    {
        $tsuku = new Tsuku(StrictnessMode::SILENT);
        $tsuku->registerFunction('boom', static function (mixed $value): never {
            throw new \RuntimeException('bad');
        });

        $this->assertSame($expected, $tsuku->process($template, ['x' => 1]));
        $this->assertFalse($tsuku->hasWarnings());
    }

    #[DataProvider('failingFunctionInConditionProvider')]
    public function testWarningModeRecordsAFailingFunctionInACondition(
        string $template,
        string $expected,
        string $failing
    ): void {
        $tsuku = new Tsuku(StrictnessMode::WARNING);
        $tsuku->registerFunction('boom', static function (mixed $value): never {
            throw new \RuntimeException('bad');
        });

        $this->assertSame($expected, $tsuku->process($template, ['x' => 1]));
        $this->assertSame(["Function '{$failing}' error: bad"], $tsuku->getWarnings());
    }

    #[DataProvider('failingFunctionInConditionProvider')]
    public function testStrictModeThrowsForAFailingFunctionInACondition(
        string $template,
        string $expected,
        string $failing
    ): void {
        $tsuku = new Tsuku(StrictnessMode::STRICT);
        $failure = new \RuntimeException('bad');
        $tsuku->registerFunction('boom', static function (mixed $value) use ($failure): never {
            throw $failure;
        });

        try {
            $tsuku->process($template, ['x' => 1]);
            $this->fail('expected TsukuException');
        } catch (TsukuException $e) {
            $this->assertSame("Function '{$failing}' error: bad", $e->getMessage());
            $this->assertSame($failure, $e->getPrevious());
        }
    }

    public function testABuiltInFunctionFailingInAConditionFollowsTheMode(): void
    {
        // An object without __toString() makes @upper() throw an Error
        $template = '@if(@upper(item) == "X")big@else small@end';
        $data = ['item' => new \stdClass()];

        $silent = new Tsuku(StrictnessMode::SILENT);
        $this->assertSame(' small', $silent->process($template, $data));

        $warning = new Tsuku(StrictnessMode::WARNING);
        $this->assertSame(' small', $warning->process($template, $data));
        $this->assertCount(1, $warning->getWarnings());
        $this->assertStringStartsWith("Function 'upper' error: ", $warning->getWarnings()[0]);
    }
}
