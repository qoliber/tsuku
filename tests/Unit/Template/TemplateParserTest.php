<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Ast\DirectiveNode;
use Qoliber\Tsuku\Ast\FunctionNode;
use Qoliber\Tsuku\Ast\TextNode;
use Qoliber\Tsuku\DirectiveRegistry;
use Qoliber\Tsuku\Exception\FunctionNotFoundException;
use Qoliber\Tsuku\Exception\ParseException;
use Qoliber\Tsuku\Function\FunctionRegistry;
use Qoliber\Tsuku\Template\TemplateParser;

class TemplateParserTest extends TestCase
{
    public function testAppliesWhitespaceControl(): void
    {
        $children = (new TemplateParser())->parse("  @if(a)\n  b\n  @end\n")->children[0];

        $this->assertInstanceOf(\Qoliber\Tsuku\Ast\IfNode::class, $children);
        $this->assertInstanceOf(TextNode::class, $children->children[0]);
        $this->assertSame("  b\n", $children->children[0]->content);
    }

    public function testRegisteredDirectivesBecomeBlocks(): void
    {
        $directives = new DirectiveRegistry();
        $directives->register('cdata', fn(string $body): string => $body);

        $withRegistry = (new TemplateParser(null, $directives))->parse('@cdata()x@end')->children[0];
        $this->assertInstanceOf(DirectiveNode::class, $withRegistry);

        $withoutRegistry = (new TemplateParser())->parse('@cdata(x)')->children[0];
        $this->assertInstanceOf(FunctionNode::class, $withoutRegistry);
    }

    public function testFunctionsAreCheckedInConditions(): void
    {
        $this->expectException(FunctionNotFoundException::class);

        (new TemplateParser(new FunctionRegistry()))->parse('@if(@nope(1))x@end');
    }

    public function testConditionErrorsReportTemplatePositions(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage("Unexpected 'b' at line 4, column 7");

        (new TemplateParser())->parse("a \\\nb\n\n@if(a b)x@end");
    }
}
