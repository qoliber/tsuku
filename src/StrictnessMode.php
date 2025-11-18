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

enum StrictnessMode: int
{
    /** Missing variables/functions return empty string, no warnings */
    case SILENT = 0;

    /** Missing variables/functions log warnings but continue processing */
    case WARNING = 1;

    /** Missing variables/functions throw exceptions immediately */
    case STRICT = 2;
}
