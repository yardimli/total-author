<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Validation\ValidationException;

class ManuscriptHtml
{
    public static function html(array $doc): string
    {
        $render = function (array $node) use (&$render): string {
            $type = $node['type'];
            if ($type === 'text') {
                $text = htmlspecialchars($node['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                foreach ($node['marks'] ?? [] as $mark) {
                    $tag = ['strong' => 'strong', 'em' => 'em', 'code' => 'code'][$mark['type']];
                    $text = "<$tag>$text</$tag>";
                }

                return $text;
            }
            if ($type === 'hard_break') {
                return '<br>';
            }
            if ($type === 'horizontal_rule') {
                return '<hr>';
            }
            $body = implode('', array_map($render, $node['content'] ?? []));
            $tag = $type === 'heading' ? 'h'.($node['attrs']['level'] ?? 1) : 'p';

            return "<$tag>$body</$tag>";
        };

        return implode("\n", array_map($render, $doc['content'] ?? []));
    }

    public static function replacement(string $content, ?string $format = null): array
    {
        $html = $format === 'html' || ($format === null && preg_match('/<\/?[a-z][^>]*>/i', $content));
        if (! $html) {
            $lines = array_values(array_filter(preg_split('/\R/u', $content), fn ($line) => trim($line) !== ''));

            return Manuscript::fromText(implode("\n", $lines));
        }
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="UTF-8"><html><body>'.$content.'</body></html>', LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $fail = fn () => throw ValidationException::withMessages(['content' => __('Unsupported manuscript HTML. Use paragraphs, headings, bold, italic, code, line breaks, or scene breaks only.')]);
        $inline = function (DOMNode $node, array $marks = []) use (&$inline, $fail): array {
            if ($node->nodeType === XML_TEXT_NODE) {
                $text = preg_replace('/[\t\r\n ]+/u', ' ', $node->nodeValue);

                return $text === '' ? [] : [array_filter(['type' => 'text', 'text' => $text, 'marks' => $marks], fn ($v) => $v !== [])];
            }
            if (! ($node instanceof DOMElement)) {
                $fail();
            }
            $tag = strtolower($node->tagName);
            if ($tag === 'br') {
                return [['type' => 'hard_break']];
            }
            $mark = ['b' => 'strong', 'strong' => 'strong', 'i' => 'em', 'em' => 'em', 'code' => 'code'][$tag] ?? null;
            if (! $mark) {
                $fail();
            }
            if (! in_array(['type' => $mark], $marks)) {
                $marks[] = ['type' => $mark];
            }
            $out = [];
            foreach ($node->childNodes as $child) {
                $out = [...$out, ...$inline($child, $marks)];
            }

            return $out;
        };
        $nodes = [];
        $pending = [];
        $flush = function () use (&$pending, &$nodes) {
            if ($pending) {
                $nodes[] = ['type' => 'paragraph', 'content' => $pending];
                $pending = [];
            }
        };
        foreach ($dom->getElementsByTagName('body')->item(0)->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE && trim($node->nodeValue) === '') {
                continue;
            }
            $tag = $node instanceof DOMElement ? strtolower($node->tagName) : '';
            if (in_array($tag, ['p', 'h1', 'h2', 'hr'])) {
                $flush();
                if ($tag === 'hr') {
                    $nodes[] = ['type' => 'horizontal_rule'];

                    continue;
                }
                $children = [];
                foreach ($node->childNodes as $child) {
                    $children = [...$children, ...$inline($child)];
                }
                $block = ['type' => $tag === 'p' ? 'paragraph' : 'heading', 'content' => $children];
                if ($tag !== 'p') {
                    $block['attrs'] = ['level' => (int) substr($tag, 1)];
                }
                $nodes[] = $block;
            } else {
                $pending = [...$pending, ...$inline($node)];
            }
        }
        $flush();
        $doc = ['type' => 'doc', 'content' => $nodes ?: [['type' => 'paragraph', 'content' => []]]];
        Manuscript::validate($doc);

        return $doc;
    }
}
