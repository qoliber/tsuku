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
 * Object exercising every access strategy (getter, is-method, method, public and private property).
 */
final class CharacterizationUser
{
    public string $nickname = 'nick';

    private string $secret = 'hidden';

    public function getName(): string
    {
        return 'x';
    }

    public function isActive(): bool
    {
        return true;
    }

    public function isInactive(): bool
    {
        return false;
    }

    public function total(): int
    {
        return 3;
    }

    public function secretLength(): int
    {
        return strlen($this->secret);
    }
}
