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

namespace Qoliber\Tsuku;

use Qoliber\Tsuku\Function\FunctionRegistry;
use Qoliber\Tsuku\Streaming\StreamingProcessor;
use Qoliber\Tsuku\Template\Template;

class Tsuku
{
    private ProcessingContext $context;

    /** Context of the last process() / processToStream() call, which holds its warnings */
    private ProcessingContext $lastContext;

    public function __construct(
        StrictnessMode $strictnessMode = StrictnessMode::SILENT,
        private readonly DirectiveRegistry $directiveRegistry = new DirectiveRegistry(),
        private readonly FormatterRegistry $formatterRegistry = new FormatterRegistry(),
        private readonly FunctionRegistry $functionRegistry = new FunctionRegistry(),
    ) {
        $this->context = new ProcessingContext($strictnessMode);
        $this->lastContext = $this->context;
    }

    /**
     * Process template string with data
     *
     * @param string $template Template text with directives
     * @param array<mixed> $data Data to process
     * @param \Qoliber\Tsuku\StrictnessMode|null $strictnessMode Override strictness mode for this call
     * @return string
     */
    public function process(string $template, array $data, ?StrictnessMode $strictnessMode = null): string
    {
        $context = $this->contextFor($strictnessMode);

        $templateObject = new Template($template);

        return $templateObject->render(
            $data,
            $context,
            $this->directiveRegistry,
            $this->formatterRegistry,
            $this->functionRegistry
        );
    }

    /**
     * Stream a template through a writer, producing output incrementally.
     *
     * The template must contain exactly one top-level @for over $rowsVariable.
     * Children before the loop become the header (rendered once with $contextData),
     * the loop body is rendered once per row with the row available as the loop
     * variable, and children after become the footer (rendered once).
     *
     * Memory usage is bounded by the size of the largest single rendered piece
     * (header, footer, or one row), regardless of how many rows are streamed.
     *
     * @param string $template Template text containing one top-level @for.
     * @param array<mixed> $contextData Data shared by header, footer, and each row.
     * @param iterable<mixed> $rows Generator/iterator producing row data; consumed once.
     * @param string $rowsVariable Name of the @for collection to stream (e.g. "products").
     * @param callable(string): void $writer Receives output incrementally.
     * @param \Qoliber\Tsuku\StrictnessMode|null $strictnessMode Override strictness mode for this call.
     * @return void
     */
    public function processToStream(
        string $template,
        array $contextData,
        iterable $rows,
        string $rowsVariable,
        callable $writer,
        ?StrictnessMode $strictnessMode = null
    ): void {
        $context = $this->contextFor($strictnessMode);

        $processor = new StreamingProcessor();
        $processor->process(
            $template,
            $contextData,
            $rows,
            $rowsVariable,
            $writer,
            $context,
            $this->functionRegistry,
            $this->directiveRegistry
        );
    }

    /**
     * Get warnings from last processing, the first ProcessingContext::WARNING_LIMIT of them
     *
     * @return array<string>
     */
    public function getWarnings(): array
    {
        return $this->lastContext->getWarnings();
    }

    /**
     * Check if there are warnings from last processing
     *
     * @return bool
     */
    public function hasWarnings(): bool
    {
        return $this->lastContext->hasWarnings();
    }

    /**
     * Count the warnings of the last processing, also those past
     * ProcessingContext::WARNING_LIMIT that getWarnings() does not return
     *
     * @return int
     */
    public function getWarningCount(): int
    {
        return $this->lastContext->getWarningCount();
    }

    /**
     * Register a custom block directive: @name(arguments) ... @end
     *
     * The handler is called as handler(string $body, mixed ...$arguments): the
     * rendered body first, then the arguments resolved like function arguments.
     * Its return value (cast to string) replaces the whole block. Errors thrown by
     * the handler follow the strictness mode, like function errors.
     *
     * @param string $name Directive name without "@"
     * @param callable $handler callable(string $body, mixed ...$arguments): mixed
     * @return self
     * @throws \InvalidArgumentException For invalid or built-in directive names
     */
    public function registerDirective(string $name, callable $handler): self
    {
        $this->directiveRegistry->register($name, $handler);
        return $this;
    }

    /**
     * Register a formatter: since 1.4.1 it is registered as a function too, so it
     * is called as @name(value, ...). It was stored but never used before.
     *
     * @deprecated 1.4.1 Use registerFunction(), which this now calls.
     * @param string $name Same rules as registerFunction()
     * @param callable $handler
     * @return self
     * @throws \InvalidArgumentException For a name that can never be called
     */
    public function registerFormatter(string $name, callable $handler): self
    {
        $this->functionRegistry->register($name, $handler);
        $this->formatterRegistry->register($name, $handler);
        return $this;
    }

    /**
     * Register a custom function, called as @name(arguments)
     *
     * @param string $name Letters, digits and underscores, not starting with a digit; not a
     *                     built-in directive and not starting with "else", "end" or "default"
     *                     (other than "default" itself), which the template would read as that directive
     * @param callable $handler
     * @return self
     * @throws \InvalidArgumentException For a name that can never be called
     */
    public function registerFunction(string $name, callable $handler): self
    {
        $this->functionRegistry->register($name, $handler);
        return $this;
    }

    /**
     * The context of one call, with no warnings from an earlier one
     *
     * @param \Qoliber\Tsuku\StrictnessMode|null $strictnessMode Override for this call
     * @return \Qoliber\Tsuku\ProcessingContext
     */
    private function contextFor(?StrictnessMode $strictnessMode): ProcessingContext
    {
        $this->context->clearWarnings();
        $this->lastContext = $strictnessMode !== null
            ? new ProcessingContext($strictnessMode)
            : $this->context;

        return $this->lastContext;
    }
}
