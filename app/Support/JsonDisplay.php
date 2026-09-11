<?php

namespace App\Support;

class JsonDisplay
{
    public static function format(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        try {
            $value = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
            // Handle older records containing an encoded JSON string as well.
            if (is_string($value) && in_array(substr(ltrim($value), 0, 1), ['{', '['])) {
                try {
                    $value = json_decode($value, false, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    // Keep the original JSON string if its contents are plain text.
                }
            }

            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\JsonException $e) {
            return $raw;
        }
    }
}
