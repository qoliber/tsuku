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

namespace Qoliber\Tsuku\Function;

use Qoliber\Tsuku\Exception\TsukuException;

class FunctionRegistry
{
    /** @var array<string, callable> */
    private array $functions = [];

    public function __construct()
    {
        $this->registerBuiltInFunctions();
    }

    /**
     * Register a function
     *
     * @param string $name
     * @param callable $handler
     * @return void
     */
    public function register(string $name, callable $handler): void
    {
        $this->functions[$name] = $handler;
    }

    /**
     * Execute a function
     *
     * @param string $name
     * @param array<mixed> $arguments
     * @return mixed
     * @throws \Qoliber\Tsuku\Exception\TsukuException
     */
    public function execute(string $name, array $arguments): mixed
    {
        if (!isset($this->functions[$name])) {
            throw new TsukuException("Function '{$name}' not found");
        }

        return ($this->functions[$name])(...$arguments);
    }

    /**
     * Check if function exists
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->functions[$name]);
    }

    /**
     * Register built-in functions
     *
     * @return void
     */
    private function registerBuiltInFunctions(): void
    {
        // String functions
        $this->register('upper', fn($str) => strtoupper((string) $str));
        $this->register('lower', fn($str) => strtolower((string) $str));
        $this->register('capitalize', fn($str) => ucfirst((string) $str));
        $this->register('trim', fn($str) => trim((string) $str));
        $this->register('concat', fn(...$args) => implode('', $args));
        $this->register('substr', fn($str, $start, $length = null) =>
            $length !== null ? substr($str, (int) $start, (int) $length) : substr($str, (int) $start));
        $this->register('replace', fn($str, $search, $replace) => str_replace($search, $replace, $str));

        // Number functions
        $this->register('abs', fn($num) => abs((float) $num));
        $this->register('round', fn($num, $precision = 0) => round((float) $num, (int) $precision));
        $this->register('ceil', fn($num) => ceil((float) $num));
        $this->register('floor', fn($num) => floor((float) $num));
        $this->register('number_format', fn($num, $decimals = 0, $decPoint = '.', $thousandsSep = ',') =>
            number_format((float) $num, (int) $decimals, $decPoint, $thousandsSep));
        $this->register('number', fn($num, $decimals = 0, $decPoint = '.', $thousandsSep = ',') =>
            number_format((float) $num, (int) $decimals, $decPoint, $thousandsSep));

        // Date functions
        $this->register('date', fn($format, $timestamp = null) =>
            date($format, $timestamp !== null ? (int) $timestamp : time()));
        $this->register('strtotime', fn($str) => strtotime($str));

        // Type functions
        $this->register('default', fn($value, $default) => empty($value) ? $default : $value);
        $this->register('length', fn($value) => is_array($value) ? count($value) : strlen((string) $value));

        // Array functions
        $this->register('join', fn($array, $glue = ',') => implode($glue, (array) $array));
        $this->register('first', function ($array) {
            if (!is_array($array) || count($array) === 0) {
                return null;
            }
            $values = array_values($array);
            return $values[0];
        });
        $this->register('last', function ($array) {
            if (!is_array($array) || count($array) === 0) {
                return null;
            }
            $values = array_values($array);
            return $values[count($values) - 1];
        });

        // Escaping functions
        $this->register('escape', fn($str, $type = 'html') => match ($type) {
            'html' => htmlspecialchars((string) $str, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'xml' => htmlspecialchars((string) $str, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            'json' => json_encode((string) $str, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
            'url' => rawurlencode((string) $str),
            'csv' => str_contains((string) $str, ',') || str_contains((string) $str, '"') || str_contains((string) $str, "\n")
                ? '"' . str_replace('"', '""', (string) $str) . '"'
                : (string) $str,
            default => htmlspecialchars((string) $str, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        });
        $this->register('html', fn($str) => htmlspecialchars((string) $str, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $this->register('xml', fn($str) => htmlspecialchars((string) $str, ENT_QUOTES | ENT_XML1, 'UTF-8'));
        $this->register('json', fn($str) => json_encode((string) $str, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));
        $this->register('url', fn($str) => rawurlencode((string) $str));
        $this->register('csv', fn($str) => str_contains((string) $str, ',') || str_contains((string) $str, '"') || str_contains((string) $str, "\n")
            ? '"' . str_replace('"', '""', (string) $str) . '"'
            : (string) $str);
    }
}
