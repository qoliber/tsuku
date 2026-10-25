# Magento Directives and Widgets in Tsuku Templates

Tsuku does not interpret Magento's `{{...}}` directives, and it does not change them either: `{{widget ...}}`,
`{{block ...}}`, `{{media ...}}`, `{{store ...}}`, `{{trans ...}}`, `{{var ...}}`, `{{config ...}}` and
`{{depend ...}}...{{/depend}}` pass through **unchanged**. That leaves three ways to handle them. The full guide is
at [tsukuru.dev/guide/widget-support](https://tsukuru.dev/guide/widget-support).

## 1. Leave Them for Magento

Render your data with Tsuku and let Magento's template filter expand the directives later (CMS blocks, CMS pages,
e-mails):

```php
use Qoliber\Tsuku\Tsuku;

$tsuku = new Tsuku();

$template = '<div class="footer">
    <p>@html(store.name)</p>
    {{widget type="Magento\Cms\Block\Widget\Block" template="widget/static_block/default.phtml" block_id="footer_links"}}
    <img src="{{media url="wysiwyg/logo.png"}}" alt="">
    <p>{{trans "Thank you, %name!" name=$customer.name}}</p>
</div>';

echo $tsuku->process($template, ['store' => ['name' => 'Fish & Chips Ltd']]);
```

Output:

```html
<div class="footer">
    <p>Fish &amp; Chips Ltd</p>
    {{widget type="Magento\Cms\Block\Widget\Block" template="widget/static_block/default.phtml" block_id="footer_links"}}
    <img src="{{media url="wysiwyg/logo.png"}}" alt="">
    <p>{{trans "Thank you, %name!" name=$customer.name}}</p>
</div>
```

Magento names the widget class with `type="..."` in `{{widget}}` (`class="..."` belongs to the `{{block}}`
directive). Two things to watch:

- `{{name}}` with nothing but a plain variable path inside is `{` + the value of `name` + `}` (`{{customer_name}}`
  renders `{Ann}`). Magento directives always have a keyword followed by attributes, so they are not affected.
- `@` is Tsuku syntax everywhere, also inside `{{...}}`: an e-mail address in `{{trans "Write to support@example.com"}}`
  or a CSS `@media` rule throws a `ParseException`. Write `@@` for a literal `@` (`support@@example.com`).

## 2. Render Widgets with Tsuku Functions

Register a function per widget and call it in the template:

```php
$tsuku->registerFunction('cms_block', function (string $blockId): string {
    // Stand-in for your CMS storage (database, cache, Magento block repository, ...)
    $blocks = ['footer_links' => '<a href="/about">About</a> | <a href="/contact">Contact</a>'];

    return $blocks[$blockId] ?? "<!-- CMS Block '{$blockId}' not found -->";
});

echo $tsuku->process('<footer>
@cms_block("footer_links")
@cms_block("missing_block")
</footer>', []);
```

Output:

```html
<footer>
<a href="/about">About</a> | <a href="/contact">Contact</a>
<!-- CMS Block 'missing_block' not found -->
</footer>
```

The return value of a function is printed as it is - Tsuku neither escapes it nor parses it again - so escape user
data inside the function. [examples/06-widget-syntax.php](examples/06-widget-syntax.php) has product list, banner
and newsletter widgets.

## 3. Expand `{{widget}}` Yourself After Rendering

Outside Magento, replace the directives in Tsuku's output. Because they come out of `process()` unchanged, no
change to Tsuku (lexer, parser) is needed:

```php
function expandWidgets(string $html, callable $render): string
{
    return preg_replace_callback('/\{\{widget\s+(.*?)\}\}/s', function (array $match) use ($render): string {
        preg_match_all('/(\w+)="([^"]*)"/', $match[1], $pairs, PREG_SET_ORDER);
        $attributes = array_column($pairs, 2, 1);

        return $render($attributes['type'] ?? '', $attributes);
    }, $html);
}

// $template from option 1
$html = $tsuku->process($template, ['store' => ['name' => 'Fish & Chips Ltd']]);

echo expandWidgets($html, function (string $type, array $attributes): string {
    return match ($type) {
        'Magento\Cms\Block\Widget\Block' => '<div class="cms-block">'
            . htmlspecialchars($attributes['block_id'] ?? '') . '</div>',
        default => '<!-- unknown widget ' . htmlspecialchars($type) . ' -->',
    };
});
```

Output:

```html
<div class="footer">
    <p>Fish &amp; Chips Ltd</p>
    <div class="cms-block">footer_links</div>
    <img src="{{media url="wysiwyg/logo.png"}}" alt="">
    <p>{{trans "Thank you, %name!" name=$customer.name}}</p>
</div>
```

Earlier versions of this document showed how to add a `{{widget}}` token to the lexer and parser. That code does
not match the 1.4.0 lexer, and none of it is needed: the directives already pass through, so option 3 gives the
same result without maintaining a modified Tsuku.
