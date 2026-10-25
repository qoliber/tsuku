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

class ParseException extends TsukuException
{
    private int $templateLine = 0;
    private int $templateColumn = 0;

    /**
     * Create an exception that knows where in the template the problem is.
     *
     * @param string $message Message, already including "line X, column Y"
     * @param int $line 1-based template line
     * @param int $column 1-based template column
     * @return self
     */
    public static function at(string $message, int $line, int $column): self
    {
        $exception = new self($message);
        $exception->templateLine = $line;
        $exception->templateColumn = $column;

        return $exception;
    }

    /**
     * Template line of the error (0 when unknown).
     *
     * @return int
     */
    public function getTemplateLine(): int
    {
        return $this->templateLine;
    }

    /**
     * Template column of the error (0 when unknown).
     *
     * @return int
     */
    public function getTemplateColumn(): int
    {
        return $this->templateColumn;
    }
}
