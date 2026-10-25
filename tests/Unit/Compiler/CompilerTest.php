<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit\Compiler;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Ast\CaseNode;
use Qoliber\Tsuku\Ast\DirectiveNode;
use Qoliber\Tsuku\Ast\IfNode;
use Qoliber\Tsuku\Ast\NodeVisitor;
use Qoliber\Tsuku\Ast\TemplateNode;
use Qoliber\Tsuku\Ast\TernaryNode;
use Qoliber\Tsuku\Ast\TextNode;
use Qoliber\Tsuku\Ast\VariableNode;
use Qoliber\Tsuku\Compiler\Compiler;
use Qoliber\Tsuku\DirectiveRegistry;
use Qoliber\Tsuku\Exception\DirectiveNotFoundException;
use Qoliber\Tsuku\Exception\FunctionNotFoundException;
use Qoliber\Tsuku\Exception\ParseException;
use Qoliber\Tsuku\Exception\TsukuException;
use Qoliber\Tsuku\Expression\LiteralExpression;
use Qoliber\Tsuku\Function\FunctionRegistry;
use Qoliber\Tsuku\ProcessingContext;
use Qoliber\Tsuku\StrictnessMode;

class CompilerTest extends TestCase
{
    public function testHandBuiltIfWithoutExpressionParsesItsCondition(): void
    {
        $ast = new TemplateNode([new IfNode('a > 1 and b', false, [new TextNode('yes')], [new TextNode('no')])]);

        $this->assertSame('yes', (new Compiler(['a' => 2, 'b' => true]))->compile($ast));
        $this->assertSame('no', (new Compiler(['a' => 2, 'b' => false]))->compile($ast));
    }

    public function testParsedExpressionWinsOverTheConditionString(): void
    {
        $ast = new TemplateNode([new IfNode('ignored', false, [new TextNode('yes')], [], new LiteralExpression(1))]);

        $this->assertSame('yes', (new Compiler([]))->compile($ast));
    }

    public function testHandBuiltTernary(): void
    {
        $ast = new TemplateNode([new TernaryNode('n > 1', '"big"', 'n')]);

        $this->assertSame('big', (new Compiler(['n' => 5]))->compile($ast));
        $this->assertSame('1', (new Compiler(['n' => 1]))->compile($ast));
    }

    public function testMalformedHandBuiltConditionThrows(): void
    {
        $this->expectException(ParseException::class);

        (new Compiler([]))->compile(new TemplateNode([new IfNode('a =', false, [])]));
    }

    public function testUnknownFunctionInHandBuiltConditionThrowsAtEvaluation(): void
    {
        $this->expectException(FunctionNotFoundException::class);

        (new Compiler([]))->compile(new TemplateNode([new IfNode('@nope(1)', false, [])]));
    }

    public function testDirective(): void
    {
        $directives = new DirectiveRegistry();
        $directives->register('wrap', fn(string $body, mixed ...$args): string => implode('|', [$body, ...$args]));
        $ast = new TemplateNode([
            new DirectiveNode(
                'wrap',
                ['"a"', '2.50', 'name', 'missing', '@upper(name)'],
                [new TextNode('<'), new VariableNode('name'), new TextNode('>')]
            ),
        ]);

        $compiler = new Compiler(['name' => 'n'], null, null, $directives);

        $this->assertSame('<n>|a|2.50|n||N', $compiler->compile($ast));
    }

    public function testDirectiveResultIsCastToString(): void
    {
        $directives = new DirectiveRegistry();
        $directives->register('count', fn(string $body): int => strlen($body));

        $this->assertSame('3', (new Compiler([], null, null, $directives))->compile(
            new TemplateNode([new DirectiveNode('count', [], [new TextNode('abc')])])
        ));
    }

    public function testDirectiveWithoutRegistryThrows(): void
    {
        $this->expectException(DirectiveNotFoundException::class);
        $this->expectExceptionMessage("Directive 'wrap' not found");

        (new Compiler([]))->compile(new TemplateNode([new DirectiveNode('wrap', [], [])]));
    }

    public function testUnregisteredDirectiveThrowsInEveryMode(): void
    {
        $this->expectException(DirectiveNotFoundException::class);

        (new Compiler([], new ProcessingContext(StrictnessMode::SILENT), null, new DirectiveRegistry()))
            ->compile(new TemplateNode([new DirectiveNode('wrap', [], [])]));
    }

    public function testDirectiveErrorInStrictMode(): void
    {
        $directives = new DirectiveRegistry();
        $directives->register('fail', function (string $body): string {
            throw new \RuntimeException('nope');
        });

        $this->expectException(TsukuException::class);
        $this->expectExceptionMessage("Directive 'fail' error: nope");

        (new Compiler([], new ProcessingContext(StrictnessMode::STRICT), null, $directives))
            ->compile(new TemplateNode([new DirectiveNode('fail', [], [])]));
    }

    public function testDirectiveErrorInWarningMode(): void
    {
        $directives = new DirectiveRegistry();
        $directives->register('fail', function (string $body): string {
            throw new \RuntimeException('nope');
        });
        $context = new ProcessingContext(StrictnessMode::WARNING);

        $output = (new Compiler([], $context, null, $directives))
            ->compile(new TemplateNode([new TextNode('a'), new DirectiveNode('fail', [], []), new TextNode('b')]));

        $this->assertSame('ab', $output);
        $this->assertSame(["Directive 'fail' error: nope"], $context->getWarnings());
    }

    public function testCaseNodeRendersNothingOnItsOwn(): void
    {
        $this->assertSame('', (new Compiler([]))->visitCase(new CaseNode(['1'], [new TextNode('x')])));
    }

    public function testFunctionReturningNonStringIsCast(): void
    {
        $functions = new FunctionRegistry();
        $functions->register('yes', fn(): bool => true);
        $functions->register('nothing', fn(): ?string => null);

        $compiler = new Compiler([], null, $functions);
        $ast = (new \Qoliber\Tsuku\Template\TemplateParser($functions))
            ->parse('[@yes()][@nothing()][@length(@split("a,b", ","))]');

        $this->assertSame('[1][][2]', $compiler->compile($ast));
    }

    public function testDirectiveNodeRequiresADirectiveVisitor(): void
    {
        $visitor = $this->createMock(NodeVisitor::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot render the custom directive @wrap');

        (new DirectiveNode('wrap', [], []))->accept($visitor);
    }
}
