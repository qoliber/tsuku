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

    public function __construct(
        StrictnessMode $strictnessMode = StrictnessMode::SILENT,
        private readonly DirectiveRegistry $directiveRegistry = new DirectiveRegistry(),
        private readonly FormatterRegistry $formatterRegistry = new FormatterRegistry(),
        private readonly FunctionRegistry $functionRegistry = new FunctionRegistry(),
    ) {
        $this->context = new ProcessingContext($strictnessMode);
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
        // Clear previous warnings
        $this->context->clearWarnings();

        // Override strictness mode if provided
        $context = $strictnessMode !== null
            ? new ProcessingContext($strictnessMode)
            : $this->context;

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
        $this->context->clearWarnings();

        $context = $strictnessMode !== null
            ? new ProcessingContext($strictnessMode)
            : $this->context;

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
     * Get warnings from last processing
     *
     * @return array<string>
     */
    public function getWarnings(): array
    {
        return $this->context->getWarnings();
    }

    /**
     * Check if there are warnings from last processing
     *
     * @return bool
     */
    public function hasWarnings(): bool
    {
        return $this->context->hasWarnings();
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
     * Register a custom formatter
     *
     * @param string $name
     * @param callable $handler
     * @return self
     */
    public function registerFormatter(string $name, callable $handler): self
    {
        $this->formatterRegistry->register($name, $handler);
        return $this;
    }

    /**
     * Register a custom function
     *
     * @param string $name
     * @param callable $handler
     * @return self
     */
    public function registerFunction(string $name, callable $handler): self
    {
        $this->functionRegistry->register($name, $handler);
        return $this;
    }
}
