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

namespace Qoliber\Tsuku\Tests\Characterization;

/**
 * Runs one examples/*.php script in a separate PHP process (the examples declare
 * global functions, so they cannot share a process) and normalises the output.
 */
final class ExampleRunner
{
    /**
     * @param string $script Absolute path of the example script
     * @return array{stdout: string, status: string}
     */
    public static function run(string $script): array
    {
        $command = [
            PHP_BINARY,
            '-d', 'display_errors=stderr',
            '-d', 'log_errors=0',
            '-d', 'xdebug.mode=off',
            '-d', 'date.timezone=UTC',
            $script,
        ];

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException('Cannot start ' . $script);
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $status = 'exit=' . $exitCode;
        if (preg_match('/Uncaught ([A-Za-z0-9_\\\\]+)/', $stderr, $matches) === 1) {
            $status .= ' uncaught=' . $matches[1];
        }

        return ['stdout' => self::normalise($stdout), 'status' => $status];
    }

    /**
     * Replace the wall-clock timestamps two examples print with a placeholder.
     *
     * @param string $output
     * @return string
     */
    public static function normalise(string $output): string
    {
        return (string) preg_replace('/\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}Z?/', '<DATETIME>', $output);
    }
}
