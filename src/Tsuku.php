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

use Qoliber\Tsuku\Template\Template;
use Qoliber\Tsuku\Function\FunctionRegistry;

class Tsuku
{
    private ProcessingContext $context;

    public function __construct(
        private readonly StrictnessMode $strictnessMode = StrictnessMode::SILENT,
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
        return $templateObject->render($data, $context, $this->directiveRegistry, $this->formatterRegistry, $this->functionRegistry);
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
     * Register a custom directive
     *
     * @param string $name
     * @param callable $handler
     * @return self
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
