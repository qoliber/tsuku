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

namespace Qoliber\Tsuku\Template;

use Qoliber\Tsuku\DirectiveRegistry;
use Qoliber\Tsuku\FormatterRegistry;
use Qoliber\Tsuku\ProcessingContext;

interface TemplateInterface
{
    /**
     * Render data using the template
     *
     * @param array<mixed> $data
     * @param \Qoliber\Tsuku\ProcessingContext $context
     * @param \Qoliber\Tsuku\DirectiveRegistry $directives
     * @param \Qoliber\Tsuku\FormatterRegistry $formatters
     * @return string
     */
    public function render(
        array $data,
        ProcessingContext $context,
        DirectiveRegistry $directives,
        FormatterRegistry $formatters
    ): string;
}
