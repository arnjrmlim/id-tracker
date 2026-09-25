<?php

namespace App\Services;

class NameNormalizationService
{
    /**
     * Normalize a full name by capitalizing the first letter of each word.
     * Handles Unicode characters, apostrophes, hyphens, and multiple spaces.
     *
     * @param string $name The raw full name input
     * @return string The normalized full name
     */
    public function normalize(string $name): string
    {
        if (empty($name)) {
            return '';
        }

        // Trim leading/trailing whitespace
        $name = trim($name);

        // Collapse multiple spaces into single spaces
        $name = preg_replace('/\s+/', ' ', $name);

        // Split into words while preserving apostrophes and hyphens
        $words = preg_split('/([\'\-\s])/', $name, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $normalized = [];
        $prevDelim = '';

        foreach ($words as $word) {
            // Check if this is a delimiter (apostrophe, hyphen, or space)
            if (in_array($word, ["'", '-', ' '])) {
                $prevDelim = $word;
                $normalized[] = $word;
                continue;
            }

            // This is a word - capitalize first letter, lowercase the rest
            if (mb_strlen($word) > 0) {
                $firstChar = mb_strtoupper(mb_substr($word, 0, 1));
                $restChars = mb_strtolower(mb_substr($word, 1));
                $normalized[] = $firstChar . $restChars;
            }
        }

        return implode('', $normalized);
    }

    /**
     * Static helper for quick access
     */
    public static function normalizeName(string $name): string
    {
        return (new self())->normalize($name);
    }
}