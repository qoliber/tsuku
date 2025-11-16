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

class ProcessingContext
{
    /** @var array<string> */
    private array $warnings = [];

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
        $this->warnings[] = $message;
    }

    /**
     * Get all warnings
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
    }

    /**
     * Check if there are any warnings
     *
     * @return bool
     */
    public function hasWarnings(): bool
    {
        return count($this->warnings) > 0;
    }
}
