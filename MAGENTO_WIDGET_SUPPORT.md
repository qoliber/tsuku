# Adding Magento-Style Widget Syntax Support

This document explains how to add Magento's `{{widget}}` syntax to Tsuku.

## Magento Syntax vs Tsuku Syntax

**Magento:**
```
{{widget class="Magento\Cms\Block\Widget\Block" template="widget/static_block/default.phtml" block_id="footer_links"}}
```

**Current Tsuku:**
```
@product_widget("Electronics", 10)
```

## Why Isn't It Supported By Default?

Tsuku uses a different syntax philosophy:
- **Magento**: `{{directive key="value" key2="value2"}}`
- **Tsuku**: `@function(arg1, arg2, arg3)`

Magento's syntax requires:
1. Different delimiter (`{{}}` vs `@`)
2. Attribute-style parsing (`key="value"` pairs)
3. Space-separated parameters

## How to Add Magento Widget Support

You have **3 options**:

### Option 1: Use Tsuku's Function Syntax (Recommended)

Create widget functions that mimic Magento widgets:

```php
$tsuku = new Tsuku();

// Register a widget function
$tsuku->registerFunction('cms_block', function(string $blockId, ?string $template = null): string {
    // Your logic to load and render CMS block
    $block = loadCmsBlock($blockId);
    return renderTemplate($template ?? 'default.phtml', ['block' => $block]);
});

// Usage in template:
// @cms_block("footer_links", "widget/static_block/default.phtml")
```

**Pros:**
- Works immediately
- Type-safe function arguments
- No lexer/parser changes needed

**Cons:**
- Different syntax than Magento
- Requires template migration

### Option 2: Pre-process Templates

Convert Magento syntax to Tsuku syntax before processing:

```php
function convertMagentoToTsuku(string $template): string {
    // Convert {{widget class="..." param="value"}} to @widget("class", "param=value")
    return preg_replace_callback(
        '/\{\{widget\s+([^}]+)\}\}/',
        function($matches) {
            $attributes = parseWidgetAttributes($matches[1]);
            $class = $attributes['class'] ?? '';
            unset($attributes['class']);

            $args = array_map(
                fn($k, $v) => "\"{$k}={$v}\"",
                array_keys($attributes),
                $attributes
            );

            return '@magento_widget("' . $class . '", ' . implode(', ', $args) . ')';
        },
        $template
    );
}

function parseWidgetAttributes(string $attrString): array {
    $attrs = [];
    preg_match_all('/(\w+)="([^"]*)"/', $attrString, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $attrs[$match[1]] = $match[2];
    }

    return $attrs;
}

// Usage:
$magentoTemplate = '{{widget class="Magento\Cms\Block\Widget\Block" block_id="footer"}}';
$tsukuTemplate = convertMagentoToTsuku($magentoTemplate);
$result = $tsuku->process($tsukuTemplate, $data);
```

**Pros:**
- Keep existing Magento templates
- No changes to Tsuku core

**Cons:**
- Extra preprocessing step
- Slight performance overhead

### Option 3: Extend Tsuku Lexer (Advanced)

Add `{{widget}}` support directly to Tsuku:

**Step 1: Modify the Lexer**

Edit `src/Lexer/Lexer.php`:

```php
// Add new token type
enum TokenType: string
{
    // ... existing types
    case MAGENTO_WIDGET = 'MAGENTO_WIDGET';
}

// In Lexer::nextToken(), add before other @ checks:
if (preg_match('/^\{\{widget\s+([^}]+)\}\}/', $remaining, $matches)) {
    $this->advance(strlen($matches[0]));
    return new Token(TokenType::MAGENTO_WIDGET, $matches[1], $startLine, $startColumn);
}
```

**Step 2: Modify the Parser**

Edit `src/Ast/Parser.php`:

```php
private function parseNode(): ?Node
{
    $token = $this->peek();

    return match ($token->type) {
        // ... existing cases
        TokenType::MAGENTO_WIDGET => $this->parseMagentoWidget(),
        // ...
    };
}

private function parseMagentoWidget(): FunctionNode
{
    $token = $this->advance();
    $attributes = $this->parseMagentoAttributes($token->value);

    // Convert to function arguments
    $args = [];
    foreach ($attributes as $key => $value) {
        $args[] = "\"{$key}={$value}\"";
    }

    return new FunctionNode('magento_widget', $args);
}

private function parseMagentoAttributes(string $attrString): array
{
    $attrs = [];
    preg_match_all('/(\w+)="([^"]*)"/', $attrString, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $attrs[$match[1]] = $match[2];
    }

    return $attrs;
}
```

**Step 3: Register Widget Handler**

```php
$tsuku->registerFunction('magento_widget', function(string ...$attributes): string {
    $params = [];
    $class = '';

    foreach ($attributes as $attr) {
        [$key, $value] = explode('=', $attr, 2);

        if ($key === 'class') {
            $class = $value;
        } else {
            $params[$key] = $value;
        }
    }

    // Render widget based on class
    return renderMagentoWidget($class, $params);
});
```

**Pros:**
- Native Magento syntax support
- No preprocessing needed
- Can use both syntaxes

**Cons:**
- Requires modifying Tsuku core
- More complex to maintain
- May complicate future updates

## Recommended Approach

**For new projects:** Use Option 1 (Tsuku's native function syntax)

**For Magento migrations:** Use Option 2 (preprocessing)

**For full Magento compatibility:** Use Option 3 (extend lexer)

## Example: Complete Magento Widget Implementation

```php
// Register Magento-compatible widget handler
$tsuku->registerFunction('magento_widget', function(string ...$attributes): string {
    // Parse attributes
    $params = [];
    $class = '';

    foreach ($attributes as $attr) {
        if (str_contains($attr, '=')) {
            [$key, $value] = explode('=', $attr, 2);
            if ($key === 'class') {
                $class = $value;
            } else {
                $params[$key] = $value;
            }
        }
    }

    // Handle different Magento widget types
    return match(true) {
        str_contains($class, 'Cms\\Block\\Widget\\Block') =>
            renderCmsBlock($params['block_id'] ?? '', $params['template'] ?? 'default.phtml'),

        str_contains($class, 'Catalog\\Block\\Product\\Widget\\NewWidget') =>
            renderNewProducts($params['products_count'] ?? 5),

        str_contains($class, 'Newsletter\\Block\\Subscribe') =>
            renderNewsletterSubscribe($params['template'] ?? 'default.phtml'),

        default => "<!-- Unknown widget class: {$class} -->"
    };
});

function renderCmsBlock(string $blockId, string $template): string {
    // Load CMS block from database/cache
    $block = getCmsBlockById($blockId);

    return <<<HTML
    <div class="cms-block" data-block-id="{$blockId}">
        {$block['content']}
    </div>
    HTML;
}

function renderNewProducts(int $count): string {
    // Load new products
    $products = getNewProducts($count);

    $html = '<div class="new-products">';
    foreach ($products as $product) {
        $html .= <<<HTML
        <div class="product">
            <h3>{$product['name']}</h3>
            <p>\${$product['price']}</p>
        </div>
        HTML;
    }
    $html .= '</div>';

    return $html;
}
```

## Testing Widget Support

```php
// If using preprocessing (Option 2):
$magentoTemplate = <<<'HTML'
<div class="footer">
    {{widget class="Magento\Cms\Block\Widget\Block" block_id="footer_links"}}
    {{widget class="Magento\Newsletter\Block\Subscribe" template="subscribe.phtml"}}
</div>
HTML;

$tsukuTemplate = convertMagentoToTsuku($magentoTemplate);
$result = $tsuku->process($tsukuTemplate, []);

// If using native function (Option 1):
$tsukuTemplate = <<<'HTML'
<div class="footer">
    @cms_block("footer_links")
    @newsletter_subscribe("subscribe.phtml")
</div>
HTML;

$result = $tsuku->process($tsukuTemplate, []);
```

## Conclusion

While Tsuku doesn't support Magento's exact `{{widget}}` syntax by default, you can:

1. **Use native Tsuku syntax** - Simpler, cleaner, type-safe
2. **Preprocess Magento templates** - Keep existing templates, convert on-the-fly
3. **Extend the lexer** - Add native `{{widget}}` support

Choose based on your needs:
- **New project?** → Option 1
- **Migrating from Magento?** → Option 2
- **Need perfect Magento compatibility?** → Option 3

The custom function system is powerful enough to handle any widget rendering logic you need!
