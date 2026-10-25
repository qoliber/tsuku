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

use Qoliber\Tsuku\Exception\DirectiveNotFoundException;

/**
 * Custom block directives: @name(arguments) ... @end
 *
 * A handler is called as handler(string $body, mixed ...$arguments) where $body is
 * the rendered content between the directive and its @end, and the arguments are
 * resolved like function arguments (quoted strings, numbers as written, variables,
 * nested function calls). The return value (cast to string) replaces the block.
 */
class DirectiveRegistry
{
    /** Names that are built-in directives and cannot be registered */
    public const RESERVED = ['for', 'if', 'unless', 'elseif', 'else', 'match', 'case', 'default', 'end'];

    /** @var array<string, callable> */
    private array $directives = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    /**
     * Register a directive
     *
     * @param string $name Directive name without "@" ([a-zA-Z_][a-zA-Z0-9_]*, not a built-in)
     * @param callable $handler callable(string $body, mixed ...$arguments): string|int|float|bool|null|\Stringable
     * @return void
     * @throws \InvalidArgumentException For names that can never match or that shadow a built-in
     */
    public function register(string $name, callable $handler): void
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*\z/', $name) !== 1) {
            throw new \InvalidArgumentException(
                "Invalid directive name '{$name}': use letters, digits and underscores, not starting with a digit"
            );
        }

        if (in_array($name, self::RESERVED, true)) {
            throw new \InvalidArgumentException("'{$name}' is a built-in directive and cannot be registered");
        }

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
     * Names of all registered directives
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->directives);
    }

    /**
     * Register default directives
     *
     * @return void
     */
    private function registerDefaults(): void
    {
        // No custom directives are registered by default
    }
}
