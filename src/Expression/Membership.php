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

namespace Qoliber\Tsuku\Expression;

/**
 * Semantics of "needle in haystack" and @contains(haystack, needle).
 *
 * Array haystack: membership, comparing scalars as strings ("7231" matches 7231).
 * String haystack: case-sensitive substring. Anything else: false.
 */
final class Membership
{
    /**
     * @param mixed $haystack
     * @param mixed $needle
     * @return bool
     */
    public static function contains(mixed $haystack, mixed $needle): bool
    {
        $needle = self::asString($needle);
        if ($needle === null) {
            return false;
        }

        if (is_string($haystack)) {
            return str_contains($haystack, $needle);
        }

        if (!is_array($haystack)) {
            return false;
        }

        foreach ($haystack as $item) {
            if (self::asString($item) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scalars and Stringable objects as string; null, arrays and other objects as null.
     *
     * @param mixed $value
     * @return string|null
     */
    private static function asString(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return null;
    }
}
