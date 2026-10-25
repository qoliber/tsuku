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

namespace Qoliber\Tsuku\Exception;

/**
 * A condition (or FunctionRegistry::execute()) refers to a function that is not registered.
 */
class FunctionNotFoundException extends TsukuException
{
    private string $functionName = '';

    /**
     * @param string $functionName
     * @param string $message
     * @return self
     */
    public static function named(string $functionName, string $message): self
    {
        $exception = new self($message);
        $exception->functionName = $functionName;

        return $exception;
    }

    /**
     * Name of the missing function (without "@").
     *
     * @return string
     */
    public function getFunctionName(): string
    {
        return $this->functionName;
    }
}
