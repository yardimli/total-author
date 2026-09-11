<?php

namespace App\Services;

use IntlBreakIterator;

class ManuscriptContext
{
    private static function position(array $blocks, int $block, int $offset): int
    {
        abort_unless(isset($blocks[$block]), 422, __('Invalid manuscript cursor.'));
        $encoded = mb_convert_encoding($blocks[$block], 'UTF-16LE', 'UTF-8');
        abort_unless($offset >= 0 && $offset * 2 <= strlen($encoded), 422, __('Invalid manuscript cursor offset.'));
        $prefix = substr($encoded, 0, $offset * 2);
        $text = mb_convert_encoding($prefix, 'UTF-8', 'UTF-16LE');
        abort_unless(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8') === $prefix, 422, __('Cursor splits a Unicode character.'));

        return array_sum(array_map('strlen', array_slice($blocks, 0, $block))) + $block + strlen($text);
    }

    public static function window(array $document, array $from, array $to, int $words): array
    {
        $blocks = array_map(fn ($node) => Manuscript::text(['content' => [$node]]), $document['content']);
        $text = implode("\n", $blocks);
        $start = self::position($blocks, $from['block'], $from['offset']);
        $end = self::position($blocks, $to['block'], $to['offset']);
        preg_match_all('/\S+/u', substr($text, 0, $start), $before, PREG_OFFSET_CAPTURE);
        preg_match_all('/\S+/u', substr($text, $end), $after, PREG_OFFSET_CAPTURE);
        $left = count($before[0]) > $words ? $before[0][count($before[0]) - $words][1] : 0;
        $last = $after[0][min($words, count($after[0])) - 1] ?? ['', 0];
        $right = $end + $last[1] + strlen($last[0]);
        // ICU boundaries are UTF-8 byte offsets. Expand outward to keep complete sentences.
        $sentences = IntlBreakIterator::createSentenceInstance('en');
        $sentences->setText($text);
        if (! $sentences->isBoundary($left)) {
            $left = $sentences->preceding($left);
        }
        if (! $sentences->isBoundary($right)) {
            $right = $sentences->following($right);
        }
        $left = max(0, $left);
        $right = $right === IntlBreakIterator::DONE ? strlen($text) : $right;

        return ['before' => substr($text, $left, $start - $left), 'text' => substr($text, $start, $end - $start), 'after' => substr($text, $end, $right - $end)];
    }
}
