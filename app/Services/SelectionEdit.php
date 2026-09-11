<?php

namespace App\Services;

class SelectionEdit
{
    private static function length(string $text): int
    {
        return strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')) / 2;
    }

    private static function slice(string $text, int $from, int $length): string
    {
        $utf16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
        $part = substr($utf16, $from * 2, $length * 2);
        $result = mb_convert_encoding($part, 'UTF-8', 'UTF-16LE');
        abort_unless(mb_convert_encoding($result, 'UTF-16LE', 'UTF-8') === $part, 422, __('Selection splits a Unicode character. Select the text again.'));

        return $result;
    }

    private static function fragment(array $node, int $from, int $to): array
    {
        $out = [];
        $position = 0;
        foreach ($node['content'] ?? [] as $child) {
            $size = $child['type'] === 'text' ? self::length($child['text']) : 1;
            $start = max(0, $from - $position);
            $end = min($size, $to - $position);
            if ($end > $start) {
                if ($child['type'] === 'text') {
                    $child['text'] = self::slice($child['text'], $start, $end - $start);
                }
                $out[] = $child;
            }
            $position += $size;
        }

        return $out;
    }

    public static function text(array $doc, array $scope): string
    {
        $blocks = $doc['content'];
        [$start, $end, $from, $to] = [$scope['from_block'], $scope['to_block'], $scope['from_offset'], $scope['to_offset']];
        abort_unless(isset($blocks[$start], $blocks[$end]) && $end >= $start, 422, __('Invalid selected range.'));
        foreach ([$start, $end] as $index) {
            abort_unless(in_array($blocks[$index]['type'], ['paragraph', 'heading']), 422, __('Select text within paragraphs or headings.'));
        }
        abort_unless($from <= self::length(Manuscript::text(['content' => [$blocks[$start]]])) && $to <= self::length(Manuscript::text(['content' => [$blocks[$end]]])) && ($start !== $end || $to > $from), 422, __('Invalid selected text offsets.'));
        $parts = [];
        for ($i = $start; $i <= $end; $i++) {
            $node = $blocks[$i];
            $node['content'] = self::fragment($node, $i === $start ? $from : 0, $i === $end ? $to : PHP_INT_MAX);
            $parts[] = Manuscript::text(['content' => [$node]]);
        }

        return implode("\n", $parts);
    }

    public static function apply(array $doc, array $scope, string $replacement): array
    {
        abort_unless(self::text($doc, $scope) === $scope['text'], 409, __('Selected text changed. Select it again.'));
        $first = $doc['content'][$scope['from_block']];
        $last = $doc['content'][$scope['to_block']];
        $prefix = self::fragment($first, 0, $scope['from_offset']);
        $suffix = self::fragment($last, $scope['to_offset'], PHP_INT_MAX);
        $nodes = Manuscript::fromText($replacement)['content'];
        $nodes[0] = array_replace($first, ['content' => [...$prefix, ...($nodes[0]['content'] ?? [])]]);
        $end = count($nodes) - 1;
        if ($end > 0) {
            $nodes[$end] = array_replace($last, ['content' => $nodes[$end]['content'] ?? []]);
        }
        $nodes[$end]['content'] = [...($nodes[$end]['content'] ?? []), ...$suffix];
        array_splice($doc['content'], $scope['from_block'], $scope['to_block'] - $scope['from_block'] + 1, $nodes);

        return $doc;
    }
}
