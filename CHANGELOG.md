# Changelog

All notable changes to Tsuku will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.4.1] - 2026-10-25

### Fixed
- **Warnings of a per-call strictness mode can be read.** `process($template, $data, StrictnessMode::WARNING)` and
  `processToStream(..., StrictnessMode::WARNING)` collected their warnings in a context that `getWarnings()` and
  `hasWarnings()` never looked at. Both now report the last call, whichever mode it ran in.
- **A function that throws inside a condition follows the strictness mode**, like a function in the output. This
  covers `@if`, `@unless`, `@elseif` and `@?{...}` conditions and the values of `@?{...}`. SILENT uses `null` as the
  function's result, WARNING also records `Function 'name' error: ...`, and STRICT throws a `TsukuException` with
  that message and the original error as `getPrevious()`. 1.3.0 and 1.4.0 let the error escape in every mode.
  An unknown function in a condition is still a `FunctionNotFoundException` when the template is parsed.
- **`@substr()`, `@replace()` and `@strtotime()` accept numbers and booleans from the data.** `@substr(sku, 0, 3)`
  with an integer SKU was a `TypeError`, so it printed nothing in SILENT mode. The same applies to the format of
  `@date()`, the separators of `@number()` and `@number_format()`, and the glue of `@join()`. A missing value is `""`.
- **`\"` inside a double-quoted string is a double quote, and `\'` inside a single-quoted string is a single
  quote.** `@concat("say \"hi\"")` printed `say \"hi\"`, and `@if(name == "say \"hi\"")` compared against the
  backslashes. This applies to function and custom directive arguments, `@?{...}` values, strings in conditions and
  `@match` / `@case` values. Every other backslash is kept as written (`"C:\path"`, `"a\\b"`).
- **`registerFunction()` refuses names that can never be called**, with an `InvalidArgumentException`, as
  `registerDirective()` does since 1.4.0. Such functions were stored and never ran:
  - names that are not identifiers (`my-func`, `1st`);
  - built-in directives (`for`, `if`, `unless`, `elseif`, `else`, `match`, `case`, `end`);
  - names starting with `else`, `end` or `default` (other than `default` itself), which the template reads as that
    directive: `@endsWith(x)` is `@end` followed by the text `sWith(x)`.

  `FunctionRegistry::register()` applies the same rules.
- **`registerFormatter()` does something**: the handler is registered as a function, so `@money(price)` calls it.
  It was stored and never used. It is deprecated in favour of `registerFunction()`.

### Tests
- 944 tests (1.4.0: 848), line coverage 99.4%, mutation score 98.8%; PHPStan max and PSR-12 clean on PHP 8.1-8.5.

### Documentation and examples
- `examples/`: every file runs without errors and prints what it claims.
  - 11 (JSON) separates array items with `@for(items as item, index)@if(index > 0),@end` instead of
    `length(...) - 1` (a ParseException since 1.4.0), quotes its timestamps (`@json(@date("c"))`), writes
    `"@@context"` / `"@@type"` for JSON-LD and uses `@if(...)...@else null@end` for optional objects; all six
    responses are valid JSON.
  - 07-csv-export, 07-number-formatting-and-escaping and 10-html-generation compute line totals, revenue and
    margins in PHP; template arithmetic such as `@number(qty * price, 2)` printed `$0.00` / `0.0%`.
  - CSV prices use `@number(value, 2, ".", "")`: `1,299.50` split the price into two columns.
  - 04 uses `@?{order.paid "[PAID]" : "[UNPAID]"}` instead of the non-existent `@ternary()` (it printed nothing).
  - 06 labels its function example as a function (it said "custom directive") and shows Magento `{{...}}`
    directives passing through; 07-csv-export and 10 no longer print stray spaces around `@match` results;
    08 prints `in_stock` / `out_of_stock` without surrounding spaces; 09 omits `image:` for a service built from a
    Dockerfile.
- README: mutation score 98.7% at 1.4.0 (not 95%); benchmark numbers re-measured with `php -n`; object lookup order
  `getX()`, `isX()`, `x()`, then the public property; the CSV example prints valid CSV; new JSON example with the
  leading-separator idiom; `@concat`, `@strtotime`, `@number_format` listed; templates have no arithmetic.
- `benchmarks/run-all.php` starts every benchmark with its own PHP binary and `-n` setting. It ran `php` from the
  `PATH` with the default php.ini, so `php -n benchmarks/run-all.php` still measured with Xdebug when it was installed.
  It also prints the CPU and whether Xdebug is loaded, and fails when a benchmark fails.
- MAGENTO_WIDGET_SUPPORT.md describes the 1.4.0 behaviour (Magento `{{...}}` directives pass through unchanged,
  `type=` for widgets); the lexer patch that no longer matched the code is removed.

### Upgrade notes
- A `registerFunction()` or `registerFormatter()` call with one of the names above now throws when it is made.
  Those functions never ran; rename them.
- `\"` in a double-quoted string now prints `"`. A template that relied on the backslash being printed must drop it.
- `registerFormatter('upper', ...)`, or any name of an existing function, now replaces that function, as
  `registerFunction()` does.
- In SILENT and WARNING mode, an error thrown by a function inside a condition no longer leaves `process()`;
  the condition sees `null`. In STRICT mode it is a `TsukuException` instead of the original error.

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
- Whitespace before the parenthesis: `@if (x)`, `@unless (x)`, `@for (x)` and `@case (x)` left a stray `)` in the
  output, `@match (x)` was a ParseException ("Expected @case or @default inside @match"). All of them now work like
  the form without the space.
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
  ParseException for `i < length(product.images) - 1` (1.3.0 printed invalid JSON for the first three and stopped at
  the fourth with a ParseException for `"@context"`).

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

[Unreleased]: https://github.com/qoliber/tsuku/compare/v1.4.1...HEAD
[1.4.1]: https://github.com/qoliber/tsuku/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/qoliber/tsuku/releases/tag/v1.4.0
[1.3.0]: https://github.com/qoliber/tsuku/releases/tag/v1.3.0
[1.2.0]: https://github.com/qoliber/tsuku/releases/tag/1.2.0
[1.1.0]: https://github.com/qoliber/tsuku/releases/tag/1.1.0
[1.0.0]: https://github.com/qoliber/tsuku/releases/tag/1.0.0
