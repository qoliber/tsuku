<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

use PHPUnit\Framework\TestCase;
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
}
