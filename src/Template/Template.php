<?php

/**
 * Tsuku - Transform data into any format
 *
 * @package   Qoliber\Tsuku
 * @author    Jakub Winkler <jwinkler@qoliber.com>
 * @copyright 2025 qoliber
 * @license   MIT
 */

declare(strict_types=1);

namespace Qoliber\Tsuku\Template;

use Qoliber\Tsuku\DirectiveRegistry;
use Qoliber\Tsuku\FormatterRegistry;
use Qoliber\Tsuku\Function\FunctionRegistry;
use Qoliber\Tsuku\ProcessingContext;
use Qoliber\Tsuku\Compiler\Compiler;

class Template implements TemplateInterface
{
    public function __construct(
        private readonly string $template,
    ) {
    }

    /**
     * Render data using the template
     *
     * @param array<mixed> $data
     * @param \Qoliber\Tsuku\ProcessingContext $context
     * @param \Qoliber\Tsuku\DirectiveRegistry $directives
     * @param \Qoliber\Tsuku\FormatterRegistry $formatters
     * @param \Qoliber\Tsuku\Function\FunctionRegistry|null $functionRegistry
     * @return string
     */
    public function render(
        array $data,
        ProcessingContext $context,
        DirectiveRegistry $directives,
        FormatterRegistry $formatters,
        ?FunctionRegistry $functionRegistry = null
    ): string {
        $functionRegistry ??= new FunctionRegistry();

        // Steps 1 + 2: tokenize (with whitespace control) and parse into AST
        $ast = (new TemplateParser($functionRegistry, $directives))->parse($this->template);

        // Step 3: Compile to output
        $compiler = new Compiler($data, $context, $functionRegistry, $directives);
        return $compiler->compile($ast);
    }
}
