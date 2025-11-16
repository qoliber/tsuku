<?php

declare(strict_types=1);

/**
 * Tsuku - Transform data into any format
 *
 * @package   Qoliber\Tsuku
 * @author    Jakub Winkler <jwinkler@qoliber.com>
 * @copyright 2024 Qoliber
 * @license   MIT
 */

namespace Qoliber\Tsuku;

use Qoliber\Tsuku\Exception\DirectiveNotFoundException;

class DirectiveRegistry
{
    /** @var array<string, callable> */
    private array $directives = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    /**
     * Register a directive
     *
     * @param string $name
     * @param callable $handler
     * @return void
     */
    public function register(string $name, callable $handler): void
    {
        $this->directives[$name] = $handler;
    }

    /**
     * Get a directive handler
     *
     * @param string $name
     * @return callable
     * @throws DirectiveNotFoundException
     */
    public function get(string $name): callable
    {
        if (!isset($this->directives[$name])) {
            throw new DirectiveNotFoundException("Directive '{$name}' not found");
        }

        return $this->directives[$name];
    }

    /**
     * Check if directive exists
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->directives[$name]);
    }

    /**
     * Register default directives
     *
     * @return void
     */
    private function registerDefaults(): void
    {
        // Will be implemented with concrete directives
    }
}
