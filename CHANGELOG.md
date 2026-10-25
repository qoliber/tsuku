# Changelog

All notable changes to Tsuku will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.0] - 2026-10-25

### Added
- **Boolean conditions** in `@if`, `@unless`, `@elseif` and `@?{...}`: `and`/`&&`, `or`/`||`, `not`/`!`,
  parentheses, and the membership operators `in` / `not in`. Precedence from lowest to highest:
  `or` < `and` < `not` < comparisons (`==` `!=` `>` `<` `>=` `<=` `in` `not in`). `and`/`or` short-circuit.
  Operands: string and number literals, dotted variable paths, function calls `@name(...)`, parenthesised
  expressions. `and`, `or`, `not` and `in` are keywords only as whole words outside strings, so variables such
  as `product.order`, `inventory`, `notes` or `android` keep working. Conditions may span several lines.
- **`in` / `not in`**: with a list on the right, membership comparing scalars as strings (`"7231"` matches
  `7231`); with a string on the right, a case-sensitive substring test; anything else is false.
- **`@elseif(condition)`** branches: `@if(a)...@elseif(b)...@else...@end` with a single `@end`.
- **Functions `@contains(haystack, needle)`** (same rules as `in`) and **`@split(string, delimiter = ",")`**
  (`""`/null give an empty list, a list is returned unchanged, an empty delimiter splits into characters), so
  `"7231" in @split(p.groups, ",")` is an exact id test.
- **`@@` prints a literal `@`** (`user@@example.com`, `@@media (...)`). A bare `@` that starts nothing is still
  a ParseException, whose message now suggests `@@`.
- **Custom block directives**: `Tsuku::registerDirective()` now works (it was a silent no-op). A registered
  `@name(arguments) ... @end` (or `@name ... @end`) calls `handler(string $body, mixed ...$arguments)` with the
  rendered body and the arguments resolved like function arguments; the return value replaces the block.
  Handler errors follow the strictness mode like function errors. Works with `processToStream()`.
- **Whitespace control** (see "Whitespace rules" below), so indented multi-line templates no longer need `\`
  line continuations.
- `ParseException::getTemplateLine()` / `getTemplateColumn()`; every ParseException message names line and
  column, conditions also name the condition.
- `FunctionNotFoundException` (extends `TsukuException`, `getFunctionName()`), thrown for unknown functions in
  conditions and by `FunctionRegistry::execute()`.
- `Lexer::tokenizeRaw()` (tokens before whitespace control), `DirectiveRegistry::names()`,
  `Template\TemplateParser` (lexer + parser, shared by `process()` and `processToStream()`).
- Test suite: characterization tests that pin the 1.3.0 behaviour that is kept (6,514 condition cases and the
  example outputs captured from the unmodified v1.3.0 code), unit tests per component and integration tests,
  848 tests in total (1.3.0: 222); line coverage 99.4% (1.3.0: 87.3%), mutation score 98.7% (1.3.0: 86.3%).

### Changed
- Conditions are parsed once per template into an expression tree instead of being matched with regular
  expressions on every evaluation; with the faster variable lookup, 100k-row feeds render 6-14% faster. Single comparisons and truthiness give exactly the 1.3.0 results (loose PHP
  comparison, numeric strings, missing variables are null, `true`/`false`/`null` are still variable paths).
- Malformed or unsupported condition syntax (`=`, `===`, arithmetic such as `x - 1`, chained comparisons,
  dangling operators, unterminated strings, empty conditions) throws `ParseException` with line and column
  instead of silently evaluating part of the condition.
- An unknown function inside a condition throws `FunctionNotFoundException` naming the function, at parse time,
  in every strictness mode (1.3.0 treated it as a missing variable). Unknown functions in plain output keep
  the 1.3.0 behaviour (removed in SILENT, warning in WARNING, exception in STRICT).
- Quotes are respected when the lexer looks for the end of `@name(...)`, `@if(...)` etc. and of `@?{...}`, so
  `@replace(name, ")", "")` and `@?{a "}" : "b"}` work. A quote right after a letter or digit (`Bob's`) is not
  a string delimiter.
- `DirectiveRegistry::register()` / `Tsuku::registerDirective()` reject names that are not identifiers or that
  are built-in directives (`InvalidArgumentException`); such registrations could never match before.
- `Compiler`, `Template::render()` and `StreamingProcessor::process()` receive the `DirectiveRegistry`
  (new optional constructor/method arguments; existing calls keep working).
- `IfNode` and `TernaryNode` carry the parsed condition (new optional constructor argument). The new
  `DirectiveNode` is visited through the separate `DirectiveVisitor` interface, so existing `NodeVisitor`
  implementations remain valid.

### Fixed
- `and`/`&&` evaluated only the first comparison; `or`/`||` and `in` conditions were silently false.
- A function call or a literal used alone as a condition (`@if(@length(items))`) was always false.
- `@if (x)`, `@for (x)`, `@match (x)` (whitespace before the parenthesis) left a stray `)` in the output.
- An operator character inside a string on the left of a comparison (`"a>b" == x`) split the condition there.
- The line break after an `@end` that follows content on its line was swallowed, merging CSV rows
  (`{sku},@if(qty > 0)in@else out@end` produced `A,inB, out`).
- Indentation of directive-only lines leaked into the output, also into the previous branch body, e.g. an
  indented multi-line `@match` rendered `A,R\n  B,B`.
- CRLF templates kept every line break after directives (only `\n` was recognised).
- `Tsuku::registerDirective()` was a silent no-op: `Template::render()` never passed the registry on.
- `{{` is not an escape and stays that way: Magento `{{widget ...}}`, `{{media ...}}`, `{{trans ...}}` pass
  through unchanged (only `{{name}}` with a plain variable inside renders `{` + value + `}`, as before).

### Whitespace rules
A line break is `\n` or `\r\n`. Block directives are `@for @if @unless @elseif @else @match @case @default
@end` and registered custom directives; everything else (text, `{variables}`, `@functions()`, `@?{}`, `@@`) is
content. `\` line continuations are removed first, exactly as in 1.2/1.3.

1. A line holding only block directives and whitespace is layout: it renders nothing, neither its
   indentation nor its line break.
2. Indentation before `@else`, `@elseif`, `@case`, `@default` or `@end` at the start of a line is dropped, also
   when content follows the directive on that line.
3. A directive other than `@end` that ends a content line removes the trailing whitespace and line break
   after it (as 1.3.0 removed a `\n`).
4. An `@end` that follows content on its line keeps the line break after it.
5. Inline block: when content precedes `@if`/`@unless`/`@match`/a custom directive on its line, whitespace
   containing a line break at the start of each branch/case body is dropped. When content also follows the
   block's `@end` on that line, the same is done at the end of each body, so the whole block sits inside the
   line (`<stock>@match(...) ... @end</stock>` renders on one line). Whitespace without a line break
   (single-line bodies such as `@else out`) is kept. `@for` bodies are never trimmed.

### Upgrade notes (output changes from 1.3.0)
- **Indented directive lines** no longer print their indentation. Templates that indented `@if`/`@for`/`@end`
  lines get the content lines' own indentation (the 1.3.0 output had extra spaces, often breaking YAML/CSV).
  If a template relied on the indentation of a directive line appearing in front of the next content (for
  example an indented `@match` line above unindented `@case` lines), indent the content line itself.
- **`@end` after content keeps its line break.** Templates that used 1.3.0's swallowing to join lines, e.g.
  `<e@if(x)\n a="1"@end\n/>`, now print the line breaks; join such lines with `\` continuations. Templates
  that added an extra blank line to work around merged rows now print that blank line.
- **Inline blocks drop the indentation of their branch bodies** (`Status: @if(x)\n   Active` gives
  `Status: Active`, 1.3.0 gave `Status:    Active`) and, when closed in front of content, also the line break
  before the `@end`.
- **CRLF templates** follow the same rules as LF templates.
- **Conditions that 1.3.0 evaluated silently and wrongly now throw** `ParseException` /
  `FunctionNotFoundException`, e.g. the JSON "comma" idiom `@if(index < length(items) - 1),@end` (never emitted
  a comma in 1.3.0) and conditions calling unregistered functions.
- **Conditions with `and`/`or`/`in`/`!`/a lone function call** now evaluate as written; check templates
  whose output depended on the previous wrong results.
- `examples/` output changes (captured 1.3.0 output in `tests/Fixtures/v1.3.0/examples`, 1.4.0 differences in
  `tests/Fixtures/v1.4.0/examples`): 02 one user per line instead of one merged line; 04, 08, 09 leaked
  indentation removed; 07 product rows no longer merged and leaked indentation removed; 10 the case text no
  longer inherits the indentation of the `@match` line; 11 now stops at its first example with a
  ParseException for `i < length(product.images) - 1` (1.3.0 printed invalid JSON and stopped at the fifth).

## [1.3.0] - 2026-05-03

### Added
- **Streaming API** (`Tsuku::processToStream`): render templates incrementally to a writer callback, materializing only one row at a time. Memory usage is bounded by the largest single rendered piece (header, footer, or row), independent of total row count. Designed for large-catalog feed exports where the existing `process()` would OOM.
  - Requires exactly one top-level `@for` over the streaming variable in the template; throws on missing, ambiguous, or nested matches.
  - Accepts any `iterable` (arrays, generators, iterators) — generators are consumed lazily.
  - Header and footer share `$contextData`; the loop body sees both `$contextData` and the current row item.
  - Supports the optional key variable: `@for(rows as row, idx)`.
  - Built-in functions, conditionals, and nested `@for` over sub-collections work the same inside the row body as in non-streaming mode.
  - StrictnessMode override is honored (STRICT/WARNING/SILENT).
  - Writer exceptions propagate to the caller.
  - Comprehensive test suite: 13 unit tests + 3 integration tests, all passing alongside the existing 206-test suite (222 total).

## [1.2.0] - 2025-11-18

### Added
- **Line Continuation Support**: Use backslash `\` to continue long lines across multiple lines
  - Works everywhere in templates (functions, variables, text)
  - Perfect for readable CSV templates without horizontal scrolling
  - Example: `@csv(name),\` continues on next line
  - Zero runtime overhead (preprocessed once at lexer level)
  - Comprehensive test suite (10 tests)
- **Documentation**:
  - Added comprehensive "Line Continuation" guide
  - Updated CSV Exports with line continuation examples

### Changed
- **CSV Export Examples**: Updated all 5 examples in `examples/07-csv-export.php` to demonstrate line continuation feature
  - Example 1: Basic Product Export - split CSV fields across lines
  - Example 2: Product Export with Categories - improved readability of complex CSV rows
  - Example 3: Customer Order Export - separated 9 CSV fields across multiple lines
  - Example 4: Inventory Report - split conditional CSV output
  - Example 5: Sales Report - separated calculation fields for better readability
- **Test Suite**: All 206 tests passing with 433 assertions

### Fixed
- Fixed typo in `LineContinuationTest.php` test case (`@upper(0"hello")` → `@upper("hello")`)

## [1.1.0] - 2025-01-18

### Added
- **Flexible Syntax Support**: Directives can now be written inline, multiline, or with indentation for improved readability
  - `@match`, `@if`, `@for`, and all directives support flexible formatting
  - Example: `@match(status) @case("active") ✓ @case("inactive") ✗ @end` (inline)
  - Example: Indented directives for nested structures
- **Enhanced Parser**: Whitespace-only tokens are now properly skipped inside `@match` blocks
- **Documentation**:
  - Added "Syntax Flexibility" section to Pattern Matching guide

### Changed
- **Examples**: Updated all examples (CSV, XML, YAML, HTML, JSON) with improved indentation and formatting
- **Tests**: Refactored MatchDirectiveTest.php to demonstrate human-readable inline syntax (17 tests)
- **Code Quality**: Applied PSR-12 code beautification to entire `src/` directory

### Fixed
- Parser now correctly handles indented `@case` and `@default` directives within `@match` blocks

## [1.0.0] - 2025-01-16

### Initial release of `Tsuku`
- Core templating engine with zero dependencies
- Variable interpolation with dot notation support (`{user.name}`)
- Control flow directives:
  - `@if`/`@else`/`@end` - Conditional rendering
  - `@unless`/`@else`/`@end` - Inverse conditionals
  - `@for` loops with optional keys
  - `@match`/`@case`/`@default`/`@end` - Pattern matching
- Built-in functions:
  - String functions: `@upper`, `@lower`, `@capitalize`, `@trim`, `@substr`, `@replace`, `@concat`
  - Number functions: `@number`, `@abs`, `@round`, `@ceil`, `@floor`
  - Date functions: `@date`, `@strtotime`
  - Array functions: `@length`, `@join`, `@first`, `@last`
  - Escaping functions: `@html`, `@xml`, `@json`, `@csv`, `@url`
  - Utility functions: `@default`
- Ternary expressions: `@?{condition "true_value" : "false_value"}`
- Smart object/array access with automatic getter detection
- Strictness modes: Silent, Warning, and Strict
- Custom function registration
- Custom directive registration
- Comprehensive test suite (196 tests, 423 assertions)
- Production-ready examples for:
  - CSV exports
  - XML generation (feeds, sitemaps, SOAP)
  - YAML configuration files
  - HTML generation
  - JSON API responses
- Complete VitePress documentation site

[1.4.0]: https://github.com/qoliber/tsuku/releases/tag/v1.4.0
[1.3.0]: https://github.com/qoliber/tsuku/releases/tag/v1.3.0
[1.2.0]: https://github.com/qoliber/tsuku/releases/tag/1.2.0
[1.1.0]: https://github.com/qoliber/tsuku/releases/tag/1.1.0
[1.0.0]: https://github.com/qoliber/tsuku/releases/tag/1.0.0
