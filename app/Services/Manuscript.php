<?php

namespace App\Services;

use App\Models\Book;
use Illuminate\Validation\ValidationException;

class Manuscript
{
    public static function fromText(string $text): array
    {
        return ['type' => 'doc', 'content' => array_map(fn ($p) => ['type' => 'paragraph', 'content' => $p === '' ? [] : [['type' => 'text', 'text' => $p]]], preg_split('/\r?\n/', $text))];
    }

    public static function text(array $doc): string
    {
        return implode("\n", array_map(fn ($node) => self::inline($node), $doc['content'] ?? []));
    }

    private static function inline(array $node): string
    {
        if (($node['type'] ?? '') === 'text') {
            return $node['text'];
        }
        if (($node['type'] ?? '') === 'hard_break') {
            return "\n";
        }

        return implode('', array_map(fn ($child) => self::inline($child), $node['content'] ?? []));
    }

    public static function validate(array $doc): void
    {
        $valid = ($doc['type'] ?? '') === 'doc' && ! empty($doc['content']) && strlen(json_encode($doc)) <= 8000000;
        $walk = function ($node, $parent = null) use (&$walk, &$valid) {
            if (! is_array($node)) {
                $valid = false;

                return;
            }
            $type = $node['type'] ?? '';
            $allowed = $parent === null ? ['doc'] : ($parent === 'doc' ? ['paragraph', 'heading', 'horizontal_rule'] : (in_array($parent, ['paragraph', 'heading']) ? ['text', 'hard_break'] : []));
            if (! in_array($type, $allowed) || array_diff(array_keys($node), ['type', 'attrs', 'marks', 'content', 'text'])) {
                $valid = false;

                return;
            }
            if ($type === 'text' && (! is_string($node['text'] ?? null) || $node['text'] === '')) {
                $valid = false;
            }
            if ($type !== 'text' && isset($node['text'])) {
                $valid = false;
            }
            if ($type === 'heading' && ! in_array($node['attrs']['level'] ?? 1, [1, 2])) {
                $valid = false;
            }
            if (isset($node['attrs']) && ($type !== 'heading' || array_diff(array_keys($node['attrs']), ['level']))) {
                $valid = false;
            }
            if (isset($node['marks']) && ! is_array($node['marks'])) {
                $valid = false;

                return;
            }
            foreach ($node['marks'] ?? [] as $mark) {
                if (! is_array($mark) || ! in_array($mark['type'] ?? '', ['em', 'strong', 'code']) || array_diff(array_keys($mark), ['type'])) {
                    $valid = false;
                }
            }
            if (isset($node['content']) && (! is_array($node['content']) || ! array_is_list($node['content']))) {
                $valid = false;

                return;
            }
            foreach ($node['content'] ?? [] as $child) {
                $walk($child, $type);
            }
        };
        $walk($doc);
        if (! $valid) {
            throw ValidationException::withMessages(['document' => __('Invalid manuscript structure.')]);
        }
    }

    public static function snapshot(Book $book, string $label): void
    {
        $book->revisions()->create(['label' => $label, 'snapshot' => [
            'document' => $book->document, 'metadata' => $book->metadata, 'title' => $book->title,
            'codex_types' => $book->codex_types, 'entries' => $book->entries()->get()->toArray(),
        ]]);
    }

    public static function checkRevision(Book $book, int $revision): void
    {
        abort_if($book->revision !== $revision, 409, __('This book changed in another tab or operation. Reload the latest version before saving. Your local draft is preserved.'));
    }
}
