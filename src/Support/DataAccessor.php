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

namespace Qoliber\Tsuku\Support;

/**
 * Dot-notation lookup used by variables, conditions and function arguments.
 *
 * @internal
 */
final class DataAccessor
{
    /**
     * Resolve "a.b.c" against arrays and objects; null when any segment is missing.
     *
     * A key that literally contains dots ("a.b") wins over the nested lookup.
     *
     * @param array<mixed> $data
     * @param string $path
     * @return mixed
     */
    public static function get(array $data, string $path): mixed
    {
        if (isset($data[$path])) {
            return $data[$path];
        }

        $value = $data;
        foreach (explode('.', $path) as $segment) {
            // Arrays inline: this is the hot path of every {variable}
            $value = is_array($value) ? ($value[$segment] ?? null) : self::segment($value, $segment);
            if ($value === null) {
                return null;
            }
        }

        return $value;
    }

    /**
     * Read one segment: array key, getX(), isX(), x(), then public property x.
     *
     * @param mixed $value
     * @param string $key
     * @return mixed
     */
    public static function segment(mixed $value, string $key): mixed
    {
        if (is_array($value)) {
            return $value[$key] ?? null;
        }

        if (!is_object($value)) {
            return null;
        }

        $getter = 'get' . ucfirst($key);
        if (method_exists($value, $getter)) {
            return $value->$getter();
        }

        $isMethod = 'is' . ucfirst($key);
        if (method_exists($value, $isMethod)) {
            return $value->$isMethod();
        }

        if (method_exists($value, $key)) {
            return $value->$key();
        }

        $reflection = new \ReflectionClass($value);
        if ($reflection->hasProperty($key) && $reflection->getProperty($key)->isPublic()) {
            return $value->$key;
        }

        return null;
    }

    /**
     * Convert a value to string exactly like a (string) cast, including the
     * "Array to string conversion" warning and the Error for objects without
     * __toString(), without casting mixed directly.
     *
     * @param mixed $value
     * @return string
     */
    public static function toString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        settype($value, 'string');
        /** @var string $value */
        return $value;
    }
}
