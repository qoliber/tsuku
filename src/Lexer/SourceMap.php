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

namespace Qoliber\Tsuku\Lexer;

/**
 * Maps byte offsets of the preprocessed template (line continuations removed)
 * back to line and column numbers of the template as written.
 */
final class SourceMap
{
    /** @var array<int, int> Offsets (in the preprocessed text) where a source line starts */
    private array $lineStarts = [0];

    /**
     * @param string $text Preprocessed template text
     * @param array<int, int> $continuations Offsets in $text where a "\" + line break was removed
     */
    public function __construct(string $text, array $continuations = [])
    {
        $offset = -1;
        while (($offset = strpos($text, "\n", $offset + 1)) !== false) {
            $this->lineStarts[] = $offset + 1;
        }

        if ($continuations !== []) {
            $this->lineStarts = array_merge($this->lineStarts, $continuations);
            sort($this->lineStarts);
        }
    }

    /**
     * @param int $offset Byte offset in the preprocessed text
     * @return array{0: int, 1: int} [line, column], both 1-based
     */
    public function position(int $offset): array
    {
        $low = 0;
        $high = count($this->lineStarts) - 1;

        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);
            if ($this->lineStarts[$middle] <= $offset) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }

        return [$low + 1, $offset - $this->lineStarts[$low] + 1];
    }
}
