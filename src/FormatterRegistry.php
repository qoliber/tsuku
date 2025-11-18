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

use Qoliber\Tsuku\Exception\FormatterNotFoundException;

class FormatterRegistry
{
    /** @var array<string, callable> */
    private array $formatters = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    /**
     * Register a formatter
     *
     * @param string $name
     * @param callable $handler
     * @return void
     */
    public function register(string $name, callable $handler): void
    {
        $this->formatters[$name] = $handler;
    }

    /**
     * Get a formatter handler
     *
     * @param string $name
     * @return callable
     * @throws FormatterNotFoundException
     */
    public function get(string $name): callable
    {
        if (!isset($this->formatters[$name])) {
            throw new FormatterNotFoundException("Formatter '{$name}' not found");
        }

        return $this->formatters[$name];
    }

    /**
     * Check if formatter exists
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->formatters[$name]);
    }

    /**
     * Register default formatters
     *
     * @return void
     */
    private function registerDefaults(): void
    {
        // Will be implemented with concrete formatters
    }
}
