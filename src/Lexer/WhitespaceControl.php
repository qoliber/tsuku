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
 * Decides which whitespace around block directives is layout (dropped) and which
 * is content (kept). It runs once per template and only trims TEXT tokens, so
 * rendering speed is not affected.
 *
 * A line break is "\n" or "\r\n"; whitespace is space, tab and "\r". Block
 * directives are @for, @if, @unless, @elseif, @else, @match, @case, @default,
 * @end and registered custom directives; everything else on a line is content.
 *
 *  1. Layout line: a line holding only block directives and whitespace renders
 *     nothing - not its indentation, not the whitespace between or after the
 *     directives, not its line break.
 *  2. Indentation before @else, @elseif, @case, @default or @end at the start of
 *     a line is dropped, also when content follows the directive on that line.
 *  3. A directive other than @end that ends a content line removes the trailing
 *     whitespace and the line break after it (1.3.0 removed a "\n" there too).
 *  4. An @end preceded by content on its line keeps the line break after it.
 *  5. Inline block: when content precedes the opening @if/@unless/@match/custom
 *     directive on its line, whitespace that contains a line break is dropped at
 *     the start of each branch or case body (the body continues the opener's
 *     line). When content also follows its @end on the @end's line, the same is
 *     done at the end of each body (the whole block sits inside one line).
 *     Whitespace without a line break is kept. @for bodies are never trimmed.
 */
final class WhitespaceControl
{
    private const BLANK = " \t\r";
    private const SPACE = " \t\r\n";

    /** @var array<int, \Qoliber\Tsuku\Lexer\Token> */
    private array $tokens = [];

    /** @var array<int, int> */
    private array $cutLeft = [];

    /** @var array<int, int> */
    private array $cutRight = [];

    /** @var array<int, bool> */
    private array $contentBefore = [];

    /** @var array<int, bool> */
    private array $contentAfter = [];

    /** @var array<int, bool> */
    private array $firstOnLine = [];

    /** @var array<int, bool> */
    private array $lastOnLine = [];

    /**
     * @param array<int, \Qoliber\Tsuku\Lexer\Token> $tokens Raw tokens (no EOF)
     * @return array<int, \Qoliber\Tsuku\Lexer\Token>
     */
    public function apply(array $tokens): array
    {
        $this->tokens = array_values($tokens);
        $this->cutLeft = [];
        $this->cutRight = [];

        $this->analyseLines();
        $this->applyLineRules();
        $this->trimInlineBlocks();

        $result = [];
        foreach ($this->tokens as $index => $token) {
            if ($token->type !== TokenType::TEXT) {
                $result[] = $token;
                continue;
            }

            $left = $this->cutLeft[$index] ?? 0;
            $right = $this->cutRight[$index] ?? 0;
            if ($left === 0 && $right === 0) {
                $result[] = $token;
                continue;
            }

            $keep = strlen($token->value) - $left - $right;
            if ($keep > 0) {
                $result[] = new Token(
                    TokenType::TEXT,
                    substr($token->value, $left, $keep),
                    $token->line,
                    $token->column,
                    $token->valueOffset
                );
            }
        }

        return $result;
    }

    /**
     * For every non-text token: is there content before/after it on its line,
     * and is it the first/last non-text token on its line.
     *
     * @return void
     */
    private function analyseLines(): void
    {
        $this->contentBefore = [];
        $this->contentAfter = [];
        $this->firstOnLine = [];
        $this->lastOnLine = [];

        $content = false;
        $atomSeen = false;
        foreach ($this->tokens as $index => $token) {
            if ($token->type === TokenType::TEXT) {
                $newline = strrpos($token->value, "\n");
                if ($newline !== false) {
                    $content = self::hasContent(substr($token->value, $newline + 1));
                    $atomSeen = false;
                } elseif (self::hasContent($token->value)) {
                    $content = true;
                }
                continue;
            }

            $this->contentBefore[$index] = $content;
            $this->firstOnLine[$index] = !$atomSeen;
            $atomSeen = true;
            if (!$token->type->isBlockDirective()) {
                $content = true;
            }
        }

        $content = false;
        $atomSeen = false;
        for ($index = count($this->tokens) - 1; $index >= 0; $index--) {
            $token = $this->tokens[$index];
            if ($token->type === TokenType::TEXT) {
                $newline = strpos($token->value, "\n");
                if ($newline !== false) {
                    $content = self::hasContent(substr($token->value, 0, $newline));
                    $atomSeen = false;
                } elseif (self::hasContent($token->value)) {
                    $content = true;
                }
                continue;
            }

            $this->contentAfter[$index] = $content;
            $this->lastOnLine[$index] = !$atomSeen;
            $atomSeen = true;
            if (!$token->type->isBlockDirective()) {
                $content = true;
            }
        }
    }

    /**
     * Rules 1-4 for every block directive.
     *
     * @return void
     */
    private function applyLineRules(): void
    {
        foreach ($this->tokens as $index => $token) {
            $type = $token->type;
            if (!$type->isBlockDirective()) {
                continue;
            }

            $before = $this->contentBefore[$index];
            $layout = !$before && !$this->contentAfter[$index];
            $closing = $type === TokenType::DIRECTIVE_END || $type->isBranch();

            // Rules 1 and 2: indentation
            if ($this->firstOnLine[$index] && ($layout || ($closing && !$before))) {
                $this->cutIndentation($index - 1);
            }

            if (!$this->lastOnLine[$index]) {
                // Rule 1: whitespace between directives of a layout line
                if ($layout) {
                    $this->cutFromLeft($index + 1, PHP_INT_MAX);
                }
                continue;
            }

            if ($layout) {
                // Rule 1: the rest of the layout line, line break included
                $this->cutLineEnd($index + 1, true);
            } elseif ($type !== TokenType::DIRECTIVE_END && !$this->contentAfter[$index]) {
                // Rule 3 (rule 4: an @end after content keeps its line break)
                $this->cutLineEnd($index + 1, false);
            }
        }
    }

    /**
     * Rule 5: match openers, branches and @end of inline @if/@unless/@match/custom blocks
     * and trim their bodies.
     *
     * @return void
     */
    private function trimInlineBlocks(): void
    {
        /** @var array<int, array{inline: bool, boundaries: array<int, int>}> $stack */
        $stack = [];

        foreach ($this->tokens as $index => $token) {
            $type = $token->type;

            if ($type->opensBlock()) {
                $inline = $type !== TokenType::DIRECTIVE_FOR && $this->contentBefore[$index];
                $stack[] = ['inline' => $inline, 'boundaries' => [$index]];
                continue;
            }

            if ($stack === []) {
                continue;
            }

            if ($type->isBranch()) {
                $stack[count($stack) - 1]['boundaries'][] = $index;
                continue;
            }

            if ($type !== TokenType::DIRECTIVE_END) {
                continue;
            }

            $frame = array_pop($stack);
            if (!$frame['inline']) {
                continue;
            }

            $boundaries = $frame['boundaries'];
            $boundaries[] = $index;
            $closedInline = $this->contentAfter[$index];
            for ($i = 0, $last = count($boundaries) - 1; $i < $last; $i++) {
                $this->trimBodyStart($boundaries[$i] + 1);
                if ($closedInline) {
                    $this->trimBodyEnd($boundaries[$i + 1] - 1);
                }
            }
        }
    }

    /**
     * Drop leading whitespace of a body when it contains a line break.
     *
     * @param int $index First token of the body
     * @return void
     */
    private function trimBodyStart(int $index): void
    {
        if ($this->tokens[$index]->type !== TokenType::TEXT) {
            return;
        }

        $value = $this->tokens[$index]->value;
        $run = strspn($value, self::SPACE);
        if (str_contains(substr($value, 0, $run), "\n")) {
            $this->cutFromLeft($index, $run);
        }
    }

    /**
     * Drop trailing whitespace of a body when it contains a line break.
     *
     * @param int $index Last token of the body (the directive itself when the body is empty)
     * @return void
     */
    private function trimBodyEnd(int $index): void
    {
        if ($this->tokens[$index]->type !== TokenType::TEXT) {
            return;
        }

        $value = $this->tokens[$index]->value;
        $trailing = substr($value, strlen(rtrim($value, self::SPACE)));
        if (str_contains($trailing, "\n")) {
            $this->cutRight[$index] = max($this->cutRight[$index] ?? 0, strlen($trailing));
        }
    }

    /**
     * Drop the whitespace between the start of the line and the directive.
     *
     * @param int $index Token before the directive
     * @return void
     */
    private function cutIndentation(int $index): void
    {
        if ($index < 0 || $this->tokens[$index]->type !== TokenType::TEXT) {
            return;
        }

        $value = $this->tokens[$index]->value;
        $newline = strrpos($value, "\n");
        $length = $newline === false ? strlen($value) : strlen($value) - $newline - 1;
        $this->cutRight[$index] = max($this->cutRight[$index] ?? 0, $length);
    }

    /**
     * Drop the whitespace after the last directive of a line and its line break.
     *
     * @param int $index Token after the directive
     * @param bool $orToEndOfInput Also drop trailing whitespace when no line break follows
     * @return void
     */
    private function cutLineEnd(int $index, bool $orToEndOfInput): void
    {
        if (!isset($this->tokens[$index]) || $this->tokens[$index]->type !== TokenType::TEXT) {
            return;
        }

        $newline = strpos($this->tokens[$index]->value, "\n");
        if ($newline !== false) {
            $this->cutFromLeft($index, $newline + 1);
        } elseif ($orToEndOfInput) {
            $this->cutFromLeft($index, PHP_INT_MAX);
        }
    }

    /**
     * @param int $index
     * @param int $length
     * @return void
     */
    private function cutFromLeft(int $index, int $length): void
    {
        if (!isset($this->tokens[$index]) || $this->tokens[$index]->type !== TokenType::TEXT) {
            return;
        }

        $this->cutLeft[$index] = max($this->cutLeft[$index] ?? 0, $length);
    }

    /**
     * @param string $text
     * @return bool
     */
    private static function hasContent(string $text): bool
    {
        return strspn($text, self::BLANK) !== strlen($text);
    }
}
