<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Template\Template;
use Qoliber\Tsuku\DirectiveRegistry;
use Qoliber\Tsuku\FormatterRegistry;
use Qoliber\Tsuku\ProcessingContext;
use Qoliber\Tsuku\StrictnessMode;

class TemplateTest extends TestCase
{
    private DirectiveRegistry $directives;
    private FormatterRegistry $formatters;
    private ProcessingContext $context;

    protected function setUp(): void
    {
        $this->directives = new DirectiveRegistry();
        $this->formatters = new FormatterRegistry();
        $this->context = new ProcessingContext(StrictnessMode::SILENT);
    }

    public function testRenderSimpleVariable(): void
    {
        $template = new Template('Hello {name}!');
        $data = ['name' => 'World'];

        $result = $template->render($data, $this->context, $this->directives, $this->formatters);

        $this->assertEquals('Hello World!', $result);
    }

    public function testRenderNestedVariable(): void
    {
        $template = new Template('{user.name} - {user.email}');
        $data = [
            'user' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        ];

        $result = $template->render($data, $this->context, $this->directives, $this->formatters);

        $this->assertEquals('John Doe - john@example.com', $result);
    }

    public function testRenderMultilineTemplate(): void
    {
        $template = new Template("Product: {product}\nPrice: \${price}\nStock: {stock}");
        $data = [
            'product' => 'Widget',
            'price' => '29.99',
            'stock' => '100',
        ];

        $result = $template->render($data, $this->context, $this->directives, $this->formatters);

        $expected = "Product: Widget\nPrice: \$29.99\nStock: 100";
        $this->assertEquals($expected, $result);
    }

    public function testMissingVariableReturnsEmpty(): void
    {
        // In SILENT mode, missing variables return empty string
        $template = new Template('{exists} and {missing}');
        $data = ['exists' => 'Found'];

        $result = $template->render($data, $this->context, $this->directives, $this->formatters);

        $this->assertEquals('Found and ', $result);
    }
}
