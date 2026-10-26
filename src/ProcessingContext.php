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

class ProcessingContext
{
    /**
     * Warnings kept per call; later ones are only counted, so a warning on every
     * row of a streamed export does not grow memory with the row count
     */
    public const WARNING_LIMIT = 1000;

    /** @var array<string> */
    private array $warnings = [];

    private int $warningCount = 0;

    public function __construct(
        private readonly StrictnessMode $strictnessMode = StrictnessMode::SILENT,
    ) {
    }

    /**
     * Get the strictness mode
     *
     * @return \Qoliber\Tsuku\StrictnessMode
     */
    public function getStrictnessMode(): StrictnessMode
    {
        return $this->strictnessMode;
    }

    /**
     * Add a warning
     *
     * @param string $message
     * @return void
     */
    public function addWarning(string $message): void
    {
        $this->warningCount++;
        if (count($this->warnings) < self::WARNING_LIMIT) {
            $this->warnings[] = $message;
        }
    }

    /**
     * Get the warnings, the first WARNING_LIMIT of them
     *
     * @return array<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Clear all warnings
     *
     * @return void
     */
    public function clearWarnings(): void
    {
        $this->warnings = [];
        $this->warningCount = 0;
    }

    /**
     * Count every warning, also those past WARNING_LIMIT that were not kept
     *
     * @return int
     */
    public function getWarningCount(): int
    {
        return $this->warningCount;
    }

    /**
     * Check if there are any warnings
     *
     * @return bool
     */
    public function hasWarnings(): bool
    {
        return $this->warningCount > 0;
    }
}
