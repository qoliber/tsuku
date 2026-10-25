<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Exception\ParseException;
use Qoliber\Tsuku\Tsuku;

/**
 * "@@" prints "@"; braces keep their 1.3.0 meaning so Magento {{...}} passes through.
 */
class EscapeAndPassthroughTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function escapes(): array
    {
        return [
            'lonely escape' => ['@@', '@'],
            'email address' => ['user@@example.com', 'user@example.com'],
            'escaped directive is text' => ['@@if(x)', '@if(x)'],
            'escaped function is text' => ['@@upper(name)', '@upper(name)'],
            'css at-rule' => [
                '@@media (max-width: 600px) { a { color: red; } }',
                '@media (max-width: 600px) { a { color: red; } }',
            ],
            'escape followed by a function' => ['@@@upper(name)', '@N'],
            'two escapes' => ['@@@@', '@@'],
            'escape inside a loop' => ["@for(items as i)\n@@{i}\n@end", "@a\n@b\n"],
            'escape is content, not layout' => ["@if(x)\n@@\n@end", "@\n"],
            'escape next to a variable' => ['{name}@@{name}', 'N@N'],
        ];
    }

    #[DataProvider('escapes')]
    public function testEscape(string $template, string $expected): void
    {
        $data = ['x' => true, 'name' => 'N', 'items' => ['a', 'b']];

        $this->assertSame($expected, (new Tsuku())->process($template, $data));
    }

    public function testBareAtIsStillRejected(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid @ syntax at line 1, column 5 (write @@ for a literal @)');

        (new Tsuku())->process('user@example.com', []);
    }

    public function testMagentoDirectivesPassThrough(): void
    {
        $template = '{{widget type="Magento\\Cms\\Block\\Widget\\Block" template="widget/static_block/default.phtml" '
            . 'block_id="footer"}} <img src="{{media url="wysiwyg/a.png"}}"/> {{trans "Hello %name" name=$name}}';

        $this->assertSame($template, (new Tsuku())->process($template, ['name' => 'N']));
    }

    public function testDoubleBracesAroundAVariableAreNotAnEscape(): void
    {
        // As in 1.3.0: "{{name}}" is "{" + {name} + "}"; a literal "{name}" needs a function
        $this->assertSame('{N}', (new Tsuku())->process('{{name}}', ['name' => 'N']));
        $this->assertSame('{name}', (new Tsuku())->process('@concat("{", "name}")', ['name' => 'N']));
    }

    public function testBraceTernaryIsLiteralText(): void
    {
        // Only @?{...} is an inline condition
        $this->assertSame('{qty > 0 ? "in" : "out"}', (new Tsuku())->process('{qty > 0 ? "in" : "out"}', ['qty' => 1]));
        $this->assertSame('in', (new Tsuku())->process('@?{qty > 0 ? "in" : "out"}', ['qty' => 1]));
    }
}
