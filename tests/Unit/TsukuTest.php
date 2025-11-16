<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class TsukuTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testProcessSimpleVariables(): void
    {
        $template = 'Hello {name}!';
        $data = ['name' => 'World'];

        $result = $this->tsuku->process($template, $data);

        $this->assertEquals('Hello World!', $result);
    }

    public function testProcessMultipleVariables(): void
    {
        $template = '{product}: ${price}';
        $data = ['product' => 'Widget', 'price' => '29.99'];

        $result = $this->tsuku->process($template, $data);

        $this->assertEquals('Widget: $29.99', $result);
    }

    public function testProcessNestedVariables(): void
    {
        $template = 'Product: {product.name}, Price: {product.price}';
        $data = [
            'product' => [
                'name' => 'Premium Widget',
                'price' => '49.99',
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertEquals('Product: Premium Widget, Price: 49.99', $result);
    }

    public function testProcessMissingVariableReturnsEmpty(): void
    {
        // With default SILENT mode, missing variables return empty string
        $template = 'Hello {name}, your email is {email}';
        $data = ['name' => 'John'];

        $result = $this->tsuku->process($template, $data);

        $this->assertEquals('Hello John, your email is ', $result);
    }

    public function testRegisterDirective(): void
    {
        $result = $this->tsuku->registerDirective('test', fn($value) => strtoupper($value));

        $this->assertSame($this->tsuku, $result);
    }

    public function testRegisterFormatter(): void
    {
        $result = $this->tsuku->registerFormatter('test', fn($value) => strtoupper($value));

        $this->assertSame($this->tsuku, $result);
    }
}
