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

use Qoliber\Tsuku\Exception\FunctionNotFoundException;
use Qoliber\Tsuku\Expression\Membership;
use Qoliber\Tsuku\Support\DataAccessor;

class FunctionRegistry
{
    /** Built-in directives: @name(...) with one of these names is read as the directive */
    private const DIRECTIVES = ['for', 'if', 'unless', 'elseif', 'else', 'match', 'case', 'end'];

    /** Directives the lexer recognises by prefix, so @endsWith(...) is read as @end */
    private const DIRECTIVE_PREFIXES = ['else', 'end', 'default'];

    /** @var array<string, callable> */
    private array $functions = [];

    public function __construct()
    {
        $this->registerBuiltInFunctions();
    }

    /**
     * Register a function, called as @name(arguments)
     *
     * @param string $name Letters, digits and underscores, not starting with a digit; not a
     *                     built-in directive and not starting with "else", "end" or "default"
     *                     (other than "default" itself), which the template would read as that directive
     * @param callable $handler
     * @return void
     * @throws \InvalidArgumentException For a name that can never be called
     */
    public function register(string $name, callable $handler): void
    {
        self::assertCallableName($name);

        $this->functions[$name] = $handler;
    }

    /**
     * Execute a function
     *
     * @param string $name
     * @param array<mixed> $arguments
     * @return mixed
     * @throws \Qoliber\Tsuku\Exception\FunctionNotFoundException
     */
    public function execute(string $name, array $arguments): mixed
    {
        if (!isset($this->functions[$name])) {
            throw FunctionNotFoundException::named($name, "Function '{$name}' not found");
        }

        return call_user_func_array($this->functions[$name], $arguments);
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
        $this->register('substr', fn($str, $start, $length = null) => $length !== null
            ? substr(self::string($str), (int) $start, (int) $length)
            : substr(self::string($str), (int) $start));
        $this->register('replace', fn($str, $search, $replace) => str_replace(
            is_array($search) ? $search : self::string($search),
            is_array($replace) ? $replace : self::string($replace),
            is_array($str) ? $str : self::string($str)
        ));

        // Number functions
        $this->register('abs', fn($num) => abs((float) $num));
        $this->register('round', fn($num, $precision = 0) => round((float) $num, (int) $precision));
        $this->register('ceil', fn($num) => ceil((float) $num));
        $this->register('floor', fn($num) => floor((float) $num));
        $number = fn($num, $decimals = 0, $decPoint = '.', $thousandsSep = ',') =>
            number_format((float) $num, (int) $decimals, self::string($decPoint), self::string($thousandsSep));
        $this->register('number_format', $number);
        $this->register('number', $number);

        // Date functions
        $this->register('date', fn($format, $timestamp = null) =>
            date(self::string($format), $timestamp !== null ? (int) $timestamp : time()));
        $this->register('strtotime', fn($str) => strtotime(self::string($str)));

        // Type functions
        $this->register('default', fn($value, $default) => empty($value) ? $default : $value);
        $this->register('length', fn($value) => is_array($value) ? count($value) : strlen((string) $value));

        // Array functions
        $this->register('join', fn($array, $glue = ',') => implode(self::string($glue), (array) $array));
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
        $html = fn($str) => htmlspecialchars((string) $str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $xml = fn($str) => htmlspecialchars((string) $str, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $json = fn($str) => json_encode((string) $str, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $url = fn($str) => rawurlencode((string) $str);
        $csv = fn($str) => str_contains((string) $str, ',') || str_contains((string) $str, '"')
            || str_contains((string) $str, "\n")
            ? '"' . str_replace('"', '""', (string) $str) . '"'
            : (string) $str;

        $this->register('escape', fn($str, $type = 'html') => match ($type) {
            'xml' => $xml($str),
            'json' => $json($str),
            'url' => $url($str),
            'csv' => $csv($str),
            default => $html($str),
        });
        $this->register('html', $html);
        $this->register('xml', $xml);
        $this->register('json', $json);
        $this->register('url', $url);
        $this->register('csv', $csv);

        // Membership: "needle in haystack" in conditions uses the same rules
        $this->register('contains', fn($haystack, $needle) => Membership::contains($haystack, $needle));
        $this->register('split', self::split(...));
    }

    /**
     * @split(value, delimiter = ","): string to list; "" or null give [], a list is returned unchanged,
     * an empty delimiter splits into UTF-8 characters.
     *
     * @param mixed $value
     * @param mixed $delimiter
     * @return array<int|string, mixed>
     */
    private static function split(mixed $value, mixed $delimiter = ','): array
    {
        if (is_array($value)) {
            return $value;
        }

        $string = DataAccessor::toString($value);
        if ($string === '') {
            return [];
        }

        $delimiter = DataAccessor::toString($delimiter);
        if ($delimiter === '') {
            return preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY) ?: str_split($string);
        }

        return explode($delimiter, $string);
    }

    /**
     * @param string $name
     * @return void
     * @throws \InvalidArgumentException
     */
    private static function assertCallableName(string $name): void
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*\z/', $name) !== 1) {
            throw new \InvalidArgumentException(
                "Invalid function name '{$name}': use letters, digits and underscores, not starting with a digit"
            );
        }

        if (in_array($name, self::DIRECTIVES, true)) {
            throw new \InvalidArgumentException(
                "'{$name}' is a built-in directive and cannot be registered as a function"
            );
        }

        foreach (self::DIRECTIVE_PREFIXES as $prefix) {
            if ($name !== 'default' && str_starts_with($name, $prefix)) {
                throw new \InvalidArgumentException(
                    "Function name '{$name}' cannot be called: @{$name}(...) is read as the @{$prefix} directive"
                    . " followed by text. Choose a name that does not start with '{$prefix}'"
                );
            }
        }
    }

    /**
     * A function argument as a string: numbers and booleans from the data are accepted, null is ""
     *
     * @param mixed $value
     * @return string
     */
    private static function string(mixed $value): string
    {
        return $value === null ? '' : DataAccessor::toString($value);
    }
}
