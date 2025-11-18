# Changelog

All notable changes to Tsuku will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.2.0]: https://github.com/qoliber/tsuku/releases/tag/v1.2.0
[1.1.0]: https://github.com/qoliber/tsuku/releases/tag/v1.1.0
[1.0.0]: https://github.com/qoliber/tsuku/releases/tag/v1.0.0
