<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Exception\ParseException;
use Qoliber\Tsuku\Exception\TsukuException;
use Qoliber\Tsuku\StrictnessMode;
use Qoliber\Tsuku\Tsuku;

/**
 * Tsuku::registerDirective(): block directives @name(args) ... @end
 */
class CustomDirectiveTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
        $this->tsuku->registerDirective('cdata', fn(string $body): string => '<![CDATA[' . $body . ']]>');
        $this->tsuku->registerDirective(
            'wrap',
            fn(string $body, string $open = '[', string $close = ']'): string => $open . $body . $close
        );
    }

    public function testHandlerReceivesRenderedBody(): void
    {
        $result = $this->tsuku->process('<name>@cdata(){name} & co@end</name>', ['name' => 'Fish']);

        $this->assertSame('<name><![CDATA[Fish & co]]></name>', $result);
    }

    public function testDirectiveWithoutParentheses(): void
    {
        // Single-line bodies are verbatim, so the space after the name is part of the body
        $this->assertSame('<![CDATA[ x]]>', $this->tsuku->process('@cdata x@end', []));
        $this->assertSame("<![CDATA[x\n]]>", $this->tsuku->process("@cdata\nx\n@end", []));
    }

    public function testArgumentsAreResolvedLikeFunctionArguments(): void
    {
        $result = $this->tsuku->process('@wrap(open, "»", 1.50)body@end|@wrap("<")b@end', ['open' => '«']);

        $this->assertSame('«body»|<b]', $result);
    }

    public function testNestingWithBuiltInDirectivesAndFunctions(): void
    {
        $template = "@for(items as i)\n<i>@cdata\n  @if(i.hot)HOT @end@upper(i.name)\n@end</i>\n@end";
        $data = ['items' => [['name' => 'a', 'hot' => true], ['name' => 'b', 'hot' => false]]];

        $this->assertSame("<i><![CDATA[HOT A]]></i>\n<i><![CDATA[B]]></i>\n", $this->tsuku->process($template, $data));
    }

    public function testDirectiveInsideDirective(): void
    {
        $this->assertSame('[<![CDATA[x]]>]', $this->tsuku->process('@wrap()@cdata()x@end@end', []));
    }

    public function testDirectiveNameShadowsFunctionOfTheSameName(): void
    {
        $this->tsuku->registerDirective('upper', fn(string $body): string => strtoupper($body) . '!');

        $this->assertSame('AB!', $this->tsuku->process('@upper()a{x}@end', ['x' => 'b']));
    }

    public function testWhitespaceRulesApply(): void
    {
        // @end on its own line: the body's last line break is part of the body
        $ownLine = "@for(rows as r)\n{r},@cdata\n    {r}\n@end\n@end";
        $this->assertSame("1,<![CDATA[1\n]]>2,<![CDATA[2\n]]>", $this->tsuku->process($ownLine, ['rows' => [1, 2]]));

        // @end right after the content: the line break follows the block
        $afterContent = "@for(rows as r)\n{r},@cdata\n    {r}@end\n@end";
        $this->assertSame(
            "1,<![CDATA[1]]>\n2,<![CDATA[2]]>\n",
            $this->tsuku->process($afterContent, ['rows' => [1, 2]])
        );
    }

    public function testStreamingUsesRegisteredDirectives(): void
    {
        $template = "<feed>\n@for(rows as r)\n  <v>@cdata()\n    {r}\n  @end</v>\n@end\n</feed>";
        $streamed = '';
        $this->tsuku->processToStream($template, [], [1, 2], 'rows', function (string $chunk) use (&$streamed): void {
            $streamed .= $chunk;
        });

        $this->assertSame("<feed>\n  <v><![CDATA[1]]></v>\n  <v><![CDATA[2]]></v>\n</feed>", $streamed);
        $this->assertSame($this->tsuku->process($template, ['rows' => [1, 2]]), $streamed);
    }

    public function testMissingEndIsAParseError(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('(@cdata opened at line 1, column 3)');

        $this->tsuku->process('a @cdata()b', []);
    }

    public function testUnregisteredNameIsStillAFunctionCall(): void
    {
        // Without registration "@shout(x)" is an (unknown) function: removed in silent mode as in 1.3.0
        $this->assertSame('[]', (new Tsuku())->process('[@shout(x)]', ['x' => 'hi']));
    }

    public function testHandlerErrorFollowsStrictnessMode(): void
    {
        $failing = fn(string $body): string => throw new \DomainException('broken');

        $silent = new Tsuku();
        $silent->registerDirective('fail', $failing);
        $this->assertSame('ab', $silent->process('a@fail()x@end' . 'b', []));

        $warning = new Tsuku(StrictnessMode::WARNING);
        $warning->registerDirective('fail', $failing);
        $this->assertSame('ab', $warning->process('a@fail()x@end' . 'b', []));
        $this->assertSame(["Directive 'fail' error: broken"], $warning->getWarnings());

        $strict = new Tsuku(StrictnessMode::STRICT);
        $strict->registerDirective('fail', $failing);
        $this->expectException(TsukuException::class);
        $this->expectExceptionMessage("Directive 'fail' error: broken");
        $strict->process('a@fail()x@end' . 'b', []);
    }

    public function testErrorsInTheBodyAreNotSwallowed(): void
    {
        $strict = new Tsuku(StrictnessMode::STRICT);
        $strict->registerDirective('cdata', fn(string $body): string => $body);

        $this->expectException(TsukuException::class);
        $this->expectExceptionMessage("Variable 'missing' not found");
        $strict->process('@cdata(){missing}@end', []);
    }

    public function testBuiltInNamesCannotBeRegistered(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'if' is a built-in directive");

        $this->tsuku->registerDirective('if', fn(string $body): string => $body);
    }

    public function testInvalidNamesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->tsuku->registerDirective('my-directive', fn(string $body): string => $body);
    }
}
