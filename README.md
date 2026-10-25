# Tsuku (つく)

**A lightweight PHP templating library for transforming data into ANY text format**

Tsuku is a powerful template processing library built with a clean **Lexer → Parser → Compiler** architecture. Transform your data into CSV, XML, JSON, XSD, or any text format you need using simple, intuitive templates.

Perfect for e-commerce exports, API responses, configuration files, and data transformations.

📖 **Documentation: [tsukuru.dev](https://tsukuru.dev)**

## Features

- 🎯 **Any text format**: CSV, XML, JSON, YAML, TOML, HTML, Markdown, INI, XSD, or custom formats
- 🔄 **Control flow**: Loops (`@for`), conditionals (`@if`, `@unless`, `@elseif`, `@else`), pattern matching (`@match`)
- 🧮 **Real conditions**: `and`/`or`/`not`, `&&`/`||`/`!`, parentheses, `in` / `not in`
- 🪺 **Deep nesting**: Unlimited levels of nested directives
- 🎨 **Smart object/array access**: Automatic getter detection, method calls, property access
- 🔧 **Custom functions and directives**: Register your own `@function()` handlers and `@block() ... @end` directives
- 🎭 **Widget support**: Build Magento-style widgets with custom functions
- 🛠️ **Clean architecture**: Lexer → Parser → Compiler pipeline (AST-based)
- 🚀 **PHP 8.1+**: Modern PHP with zero dependencies
- ✅ **Production-ready**: 848 tests (including characterization tests against 1.3.0), 95% mutation score
- 📦 **Predictable whitespace**: directive-only lines vanish, content lines are kept exactly as written
- ⚡ **Fast**: Single-pass compilation, efficient AST walking
- 🔒 **Type-safe**: Full PHP 8.1+ type hints and strict types

## Performance

Tsuku is **fast** - designed for high-volume data transformations:

| Benchmark | Performance | Throughput |
|-----------|-------------|------------|
| **Simple templates** | 0.2ms per render | **~5,000 renders/sec** |
| **Complex templates** | 1.0ms per render | **~1,000 renders/sec** |
| **1,000 variables** | 1.8ms per render | **~550 renders/sec** |
| **CSV export (1,000 products)** | 3.9ms per export | **~250 exports/sec** |

**Real-world capacity:**
- **250,000+ products/second** for CSV exports
- Sub-millisecond rendering for typical templates
- Low memory footprint (~60KB per render)

Run benchmarks yourself:
```bash
php benchmarks/run-all.php
```

See [benchmarks/](benchmarks/) for detailed performance tests.

## Requirements

- PHP 8.1 or higher

## Installation

```bash
composer require qoliber/tsuku
```

## Quick Start

### Simple Variables

```php
use Qoliber\Tsuku\Tsuku;

$data = ['product' => 'Widget', 'price' => 29.99];

$template = 'Product: {product}, Price: ${price}';

$tsuku = new Tsuku();
echo $tsuku->process($template, $data);
// Output: Product: Widget, Price: $29.99
```

### Loops with `@for`

```php
$template = 'Products:
@for(products as product)
- {product.name}: ${product.price}
@end';

$data = [
    'products' => [
        ['name' => 'Widget A', 'price' => '29.99'],
        ['name' => 'Widget B', 'price' => '39.99'],
    ],
];

echo $tsuku->process($template, $data);
// Output:
// Products:
// - Widget A: $29.99
// - Widget B: $39.99
```

### Conditionals with `@if` and `@else`

```php
$template = '@for(items as item)
{item.name}: @if(item.stock > 0)
✓ Available
@else
✗ Out of Stock
@end
@end';
```

### Smart Object/Array Access

```php
// Works with both arrays AND objects!
class Product {
    private $price = 99.99;
    public function getPrice() { return $this->price; }
    public function isAvailable() { return true; }
}

$template = 'Price: ${product.price}, Available: {product.available}';
$tsuku->process($template, ['product' => new Product()]);
// Output: Price: $99.99, Available: 1
```

### Custom Functions

```php
// Register your own functions
$tsuku->registerFunction('currency', fn($amount, $code = 'USD') =>
    match($code) {
        'USD' => '$' . number_format($amount, 2),
        'EUR' => '€' . number_format($amount, 2),
        default => $code . ' ' . number_format($amount, 2)
    }
);

$template = 'Total: @currency(price, "EUR")';
$tsuku->process($template, ['price' => 99.99]);
// Output: Total: €99.99
```

## Streaming Large Datasets

For large catalogs (10k+ rows) where holding the whole rendered output in memory is impractical, use `processToStream`. It renders the template incrementally — header once, each row in turn, footer once — and writes each piece through a callback as soon as it's ready.

```php
$tsuku = new Tsuku();

$template = '<?xml version="1.0"?>
<feed currency="{currency}">
@for(products as product)
  <item>
    <sku>{product.sku}</sku>
    <price>{product.price} {currency}</price>
  </item>
@end
</feed>';

$rows = function (): Generator {
    // yield products lazily from your data source
    foreach (loadProductsFromDb() as $row) {
        yield $row;
    }
};

$handle = fopen('feed.xml', 'wb');
$tsuku->processToStream(
    $template,
    ['currency' => 'EUR'],   // shared by header, footer, and each row
    $rows(),                  // any iterable; generators are consumed lazily
    'products',               // name of the @for collection to stream
    fn(string $chunk) => fwrite($handle, $chunk)
);
fclose($handle);
```

**Constraints:**
- Template must contain exactly **one top-level** `@for` over the streaming variable. Multiple matches or nesting inside `@if`/`@for` throws.
- The streamed iterable is consumed once. Generators cannot be rewound.
- Memory usage is bounded by the size of the largest single piece (header, footer, or one row), regardless of total row count.

## Real-World Examples

### CSV Export with Escaping

```php
$template = 'SKU,Name,Price,Stock
@for(products as product)
@csv(product.sku),@csv(product.name),$@number(product.price, 2),{product.stock}
@end';

$data = [
    'products' => [
        ['sku' => 'WID-001', 'name' => 'Widget', 'price' => 29.99, 'stock' => '100'],
        ['sku' => 'GAD-002', 'name' => 'Gadget, Premium', 'price' => 1299.50, 'stock' => '50'],
    ],
];

file_put_contents('export.csv', $tsuku->process($template, $data));
// Output:
// SKU,Name,Price,Stock
// WID-001,Widget,$29.99,100
// GAD-002,"Gadget, Premium",$1,299.50,50
```

### XML Product Catalog

```php
$template = '<?xml version="1.0"?>
<catalog>
@for(products as product)
  <product id="{product.id}">
    <name>{product.name}</name>
    <price>{product.price}</price>
@if(product.stock > 0)
    <availability>in-stock</availability>
@end
  </product>
@end
</catalog>';
```

### YAML Configuration

```php
$template = 'services:
@for(services as service)
  {service.name}:
    image: {service.image}
    @if(service.ports)
    ports:
      @for(service.ports as port)
      - "{port}"
      @end
    @end
@end';
// Directive-only lines produce no output, so they can be indented like the YAML around them
```

### HTML Product List with XSS Protection

```php
$template = '<ul class="products">
@for(products as product)
  <li>
    <h3>@html(product.name)</h3>
    <p>$@number(product.price, 2)</p>
    <div class="description">@html(product.description)</div>
    @if(product.stock > 0)
    <span class="in-stock">Available</span>
    @else
    <span class="out-of-stock">Out of Stock</span>
    @end
  </li>
@end
</ul>';

$data = [
    'products' => [
        [
            'name' => 'Premium Widget',
            'price' => 1299.99,
            'description' => 'A <strong>powerful</strong> widget',
            'stock' => 5,
        ],
    ],
];

// Output: HTML entities escaped to prevent XSS
// <h3>Premium Widget</h3>
// <p>$1,299.99</p>
// <div class="description">A &lt;strong&gt;powerful&lt;/strong&gt; widget</div>
```

## Template Syntax

### Variables

Use `{variableName}` or `{object.property}` for dot notation:

```
{name}
{product.name}
{category.products.0.name}
```

**Smart Object/Array Access:**
```php
// All of these work:
{product.price}     // Array: $product['price'] OR Object: $product->getPrice()
{user.name}         // Array: $user['name'] OR Object: $user->getName()
{product.available} // Array: $product['available'] OR Object: $product->isAvailable()
{item.total}        // Array: $item['total'] OR Object: $item->total() OR $item->getTotal()
```

### For Loops

```
@for(collection as item)
  {item.property}
@end
```

With key/value (value first, then key):
```
@for(items as item, key)
  {key}: {item}
@end
```

### Conditionals

**If/Else:**
```
@if(variable > 0)
  Content when true
@else
  Content when false
@end
```

**Unless:**
```
@unless(variable > 0)
  Content when false
@else
  Content when true
@end
```

**Match (Pattern Matching):**
```
@match(status)
@case("active")
  ✓ Active
@case("pending")
  ⏳ Pending
@case("suspended")
  ⚠ Suspended
@default
  ❌ Unknown
@end
```

**Match with multiple values:**
```
@match(user.role)
@case("admin", "moderator")
  Full Access
@case("user", "guest")
  Limited Access
@default
  No Access
@end
```

**Else-if:**
```
@if(product.qty > 10)
  In stock
@elseif(product.qty > 0)
  Low stock
@else
  Sold out
@end
```

**Conditions** (in `@if`, `@unless`, `@elseif` and `@?{...}`):

| Operator | Meaning |
|----------|---------|
| `==` `!=` `>` `<` `>=` `<=` | PHP loose comparison (numeric strings compare as numbers) |
| `in`, `not in` | list on the right: membership, comparing as strings (`"7231" in ids` matches `7231`); string on the right: case-sensitive substring; anything else: false |
| `not`, `!` | negation |
| `and`, `&&` | both (short-circuit) |
| `or`, `\|\|` | either (short-circuit) |
| `( ... )` | grouping |

Precedence from lowest to highest: `or` < `and` < `not` < comparisons, so `not a == b` means `not (a == b)`.
Operands are string and number literals, variable paths, function calls and parenthesised expressions;
a condition may span several lines. `and`, `or`, `not` and `in` are keywords only as whole words, so
`product.order`, `inventory` or `notes` are ordinary variables. `true`, `false` and `null` are *not*
literals (they are looked up as variables, as in earlier versions): write `@if(flag)` / `@if(not flag)`.

```
@if(product.qty > 0 and ("sale" in product.tags or product.price < 10))
@if("7231" in @split(product.groups, ","))   // exact id membership
@if(@length(product.images))                  // a function result is tested for truthiness
@?{product.qty > 0 and product.visible "in stock" : "out of stock"}
```

Malformed conditions (`=`, `x - 1`, `a < b < c`, a dangling `and`, an unknown function, ...) throw a
`ParseException` (or `FunctionNotFoundException`) naming the line and column instead of rendering
something wrong.

### Built-in Functions

**String functions:**
```
@upper(text)                 // HELLO
@lower(text)                 // hello
@capitalize(text)            // Hello
@trim(text)                  // Remove whitespace
@substr(text, start, length) // Extract substring
@replace(text, search, replace) // Replace text
```

**Number functions:**
```
@number(value, decimals, decPoint, thousandsSep)  // 1,234.56
@number(1234.567, 2)                              // 1,234.57
@number(1234.567, 2, ",", ".")                    // 1.234,57
@round(value, precision)                          // Round number
@ceil(value)                                      // Round up
@floor(value)                                     // Round down
@abs(value)                                       // Absolute value
```

**Array functions:**
```
@join(items, ", ")     // Join with separator
@length(items)         // Count items
@first(items)          // First element
@last(items)           // Last element
```

**Escaping functions:**
```
@html(text)            // HTML-safe: &lt;script&gt;
@xml(text)             // XML-safe escaping
@json(text)            // JSON-safe escaping
@url(text)             // URL encoding: Hello%20World
@csv(text)             // CSV escaping with quotes
@escape(text, "html")  // Generic escape (html/xml/json/url/csv)
```

**Date/Utility functions:**
```
@date("Y-m-d", timestamp)     // Format date
@default(value, "fallback")   // Use fallback if empty
```

**Membership functions:**
```
@contains(haystack, needle)   // Same rules as "needle in haystack": list membership or substring
@split(value, ",")            // "a,b" -> ["a", "b"]; "" -> []; the delimiter defaults to ","
@join(@split(tags, ";"), " | ")
```

### Custom Functions

Register your own:
```php
$tsuku->registerFunction('badge', function(string $text, string $color = 'blue'): string {
    return "<span class=\"badge badge-{$color}\">{$text}</span>";
});

// Use in template:
// @badge(status, "green")
```

### Custom Directives

Directives wrap a block of the template. The handler receives the rendered body first, then the
arguments (resolved like function arguments), and returns the output that replaces the block:

```php
$tsuku->registerDirective('cdata', fn(string $body): string => '<![CDATA[' . $body . ']]>');
$tsuku->registerDirective('wrap', fn(string $body, string $open, string $close): string => $open . $body . $close);

// <title>@cdata
//     {product.name}
// @end</title>                     ->  <title><![CDATA[Fish & Chips]]></title>
// @wrap("[", "]"){product.sku}@end  ->  [WID-001]
```

A directive can be written `@name(arguments) ... @end` or, without arguments, `@name ... @end`.
Registered names take precedence over functions of the same name; built-in names (`if`, `for`, ...)
cannot be registered. Errors thrown by a handler follow the strictness mode, like function errors.

### Whitespace Control

Templates can be indented freely; the rules decide which whitespace around directives is output:

1. A line holding only directives (and spaces/tabs) produces no output at all - not its indentation,
   not its line break.
2. Indentation before `@else`, `@elseif`, `@case`, `@default` and `@end` at the start of a line is dropped.
3. A directive other than `@end` at the end of a content line takes that line break with it.
4. An `@end` that follows content on its line keeps the line break after it.
5. In an *inline* block (content before the opening directive on its line), whitespace containing a line
   break at the start of each branch is dropped - and at the end of each branch too when content follows
   the block's `@end` on its line. Single-line branches (`@else out`) stay exactly as written.

```
@for(products as p)
{p.sku},@match(p.status)
    @case("active")
        Available
    @default
        Unavailable
@end
@end
```
renders one row per product (`WID-001,Available`). `\` line continuations still join lines first;
`\r\n` line breaks are handled like `\n`. Full rules: see CHANGELOG.md (1.4.0).

### Escaping

`@@` prints a literal `@` (`user@@example.com`, `@@media (max-width: 600px)`). Braces need no escaping:
`{...}` is a variable only when it contains a plain path, so JSON, CSS and Magento directives such as
`{{widget ...}}` or `{{media url="..."}}` pass through unchanged. (`{{name}}` is `{` + the value of
`name` + `}`; to print a literal `{name}` use `@concat("{", "name}")`.)

### Deep Nesting

Nest directives as deep as you need:

```
@for(categories as category)
  Category: {category.name}
  @for(category.products as product)
    Product: {product.name}
    @for(product.variants as variant)
      Variant: {variant.sku} - ${variant.price}
    @end
  @end
@end
```

## Architecture & Design

Tsuku uses a clean **three-stage compiler pipeline** inspired by traditional programming language design:

### The Pipeline

```
Template String → Lexer (+ whitespace control) → Tokens → Parser → AST → Compiler → Output String
```

#### 1. **Lexer (Lexical Analyzer)**
> **What it means:** "Lexer" comes from "lexical analysis" - breaking text into meaningful chunks

**Location:** `src/Lexer/Lexer.php`

The Lexer reads the raw template string character by character and breaks it into **tokens** (meaningful units):

```php
Input:  "Hello {name}, @if(admin)welcome@end"

Tokens: [
  TEXT("Hello "),
  VARIABLE("name"),
  TEXT(", "),
  DIRECTIVE_IF("admin"),
  TEXT("welcome"),
  DIRECTIVE_END
]
```

**Why?** Makes parsing easier by converting a string into structured chunks.

#### 2. **Parser (Syntax Analyzer)**
> **What it means:** Builds a tree structure showing how pieces relate to each other

**Location:** `src/Ast/Parser.php`

The Parser takes tokens and builds an **AST (Abstract Syntax Tree)** - a tree structure representing the template's logical structure:

```php
Tokens: [TEXT("Hello "), VARIABLE("name"), DIRECTIVE_IF(...)]

AST:
TemplateNode
├── TextNode("Hello ")
├── VariableNode("name")
└── IfNode(condition: "admin")
    └── TextNode("welcome")
```

**Why?** The tree structure makes it easy to handle nesting and execute directives in the correct order.

#### 3. **Compiler (Code Generator)**
> **What it means:** Walks the tree and generates the final output

**Location:** `src/Compiler/Compiler.php`

The Compiler **walks** the AST tree using the **Visitor Pattern** and generates the output string:

```php
AST Tree → Visitor Pattern → Final Output

TemplateNode.accept(compiler)
  ├── TextNode.accept(compiler) → "Hello "
  ├── VariableNode.accept(compiler) → "John"  (looks up data)
  └── IfNode.accept(compiler)
      └── if (condition) TextNode.accept(compiler) → "welcome"

Output: "Hello John, welcome"
```

**Why?** Clean separation: data lookup, conditionals, loops all handled in one place.

### Key Concepts Explained

**AST (Abstract Syntax Tree)**
- A tree representation of your template structure
- Each node = one piece (text, variable, loop, condition)
- Example: `@if(x)@for(items)...@end@end` becomes a tree with IfNode containing ForNode

**Node**
- One element in the AST tree
- Types: `TextNode`, `VariableNode`, `ForNode`, `IfNode`, `FunctionNode`, etc.
- Each node knows how to compile itself

**Token**
- Smallest meaningful unit from Lexer
- Like words in a sentence
- Types: `TEXT`, `VARIABLE`, `DIRECTIVE_IF`, `DIRECTIVE_FOR`, etc.

**Visitor Pattern**
- Design pattern where nodes "accept" a visitor (the compiler)
- Allows separating tree structure from processing logic
- Each node has `accept(NodeVisitor $visitor)` method

### Benefits of This Architecture

✅ **Predictable whitespace** - layout is decided once, while lexing; rendering never re-scans text
✅ **Proper nesting validation** - Parser builds correct tree or throws error
✅ **Clean separation of concerns** - Each stage has one job
✅ **Easy to extend** - Add new node types without breaking existing code
✅ **Fast execution** - Single pass through the tree
✅ **Type safety** - PHP 8.1+ types ensure correctness

### Class Naming Conventions

Tsuku follows industry-standard naming for compiler components:

| Class Name | Purpose | Location |
|------------|---------|----------|
| `Lexer` | Lexical analyzer - breaks text into tokens | `src/Lexer/` |
| `Token` | One meaningful unit (like a word) | `src/Lexer/Token.php` |
| `TokenType` | Enum of all token types | `src/Lexer/TokenType.php` |
| `Parser` | Syntax analyzer - builds AST from tokens | `src/Ast/Parser.php` |
| `*Node` | AST tree nodes (`TextNode`, `ForNode`, etc.) | `src/Ast/` |
| `NodeVisitor` | Interface for visiting AST nodes | `src/Ast/NodeVisitor.php` |
| `Compiler` | Code generator - walks AST to create output | `src/Compiler/Compiler.php` |
| `Tsuku` | Main API entry point | `src/Tsuku.php` |

**Naming Philosophy:**
- **Lexer/Parser/Compiler** - Standard compiler pipeline terms
- **Node suffix** - Indicates AST node type (`TextNode`, `IfNode`)
- **Registry suffix** - Stores and manages items (`FunctionRegistry`)
- **Visitor suffix** - Implements visitor pattern (`NodeVisitor`)
- **Exception suffix** - Error types (`TsukuException`, `ParseException`)

### How It All Works Together

```php
$tsuku = new Tsuku();
$result = $tsuku->process('@if(admin){name}@end', ['admin' => true, 'name' => 'John']);

// Internally:
// 1. Lexer::tokenize() → [DIRECTIVE_IF("admin"), VARIABLE("name"), DIRECTIVE_END]
// 2. Parser::parse() → IfNode(condition: "admin", children: [VariableNode("name")])
// 3. Compiler::compile() → Walks tree:
//    - IfNode: evaluate condition (true) → execute children
//    - VariableNode: lookup "name" in data → "John"
// 4. Output: "John"
```

This architecture is the same used by:
- Programming languages (PHP, JavaScript, Python)
- Template engines (Twig, Blade, Smarty)
- Markup processors (Markdown, BBCode)

**Further Reading:**
- [Compilers: Principles, Techniques, and Tools](https://en.wikipedia.org/wiki/Compilers:_Principles,_Techniques,_and_Tools) (Dragon Book)
- [Abstract Syntax Tree](https://en.wikipedia.org/wiki/Abstract_syntax_tree)
- [Visitor Pattern](https://refactoring.guru/design-patterns/visitor)

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run mutation testing
composer test:mutation

# Run static analysis
composer analyse

# Check code style
composer cs:check

# Fix code style
composer cs:fix
```

## License

MIT License - see LICENSE file for details

## Credits

Created by [qoliber](https://qoliber.com) - Like a hummingbird (koliber), swift and precise in data transformation.

**Tsuku** (つく) means "to create" or "to make" in Japanese, reflecting the library's purpose of creating text output from data.
