<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\ProcessingContext;
use Qoliber\Tsuku\StrictnessMode;

class ProcessingContextTest extends TestCase
{
    public function testWarningsAreKeptInOrderAndCounted(): void
    {
        $context = new ProcessingContext(StrictnessMode::WARNING);

        $this->assertFalse($context->hasWarnings());
        $this->assertSame(0, $context->getWarningCount());

        $context->addWarning('first');
        $context->addWarning('first');
        $context->addWarning('second');

        $this->assertTrue($context->hasWarnings());
        $this->assertSame(['first', 'first', 'second'], $context->getWarnings());
        $this->assertSame(3, $context->getWarningCount());
    }

    public function testOnlyTheFirstWarningsUpToTheLimitAreKept(): void
    {
        $context = new ProcessingContext(StrictnessMode::WARNING);
        $total = ProcessingContext::WARNING_LIMIT + 5;

        for ($i = 1; $i <= $total; $i++) {
            $context->addWarning("warning {$i}");
        }

        $warnings = $context->getWarnings();
        $this->assertCount(ProcessingContext::WARNING_LIMIT, $warnings);
        $this->assertSame('warning 1', $warnings[0]);
        $last = ProcessingContext::WARNING_LIMIT;
        $this->assertSame("warning {$last}", $warnings[$last - 1]);
        $this->assertSame($total, $context->getWarningCount());
        $this->assertTrue($context->hasWarnings());
    }

    public function testTheLimitIsAThousand(): void
    {
        $this->assertSame(1000, ProcessingContext::WARNING_LIMIT);
    }

    public function testClearingStartsTheCountOver(): void
    {
        $context = new ProcessingContext(StrictnessMode::WARNING);
        for ($i = 0; $i <= ProcessingContext::WARNING_LIMIT; $i++) {
            $context->addWarning('again');
        }

        $context->clearWarnings();

        $this->assertFalse($context->hasWarnings());
        $this->assertSame([], $context->getWarnings());
        $this->assertSame(0, $context->getWarningCount());

        $context->addWarning('after');
        $this->assertSame(['after'], $context->getWarnings());
        $this->assertSame(1, $context->getWarningCount());
    }
}
