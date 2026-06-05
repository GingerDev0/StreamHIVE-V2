<?php

declare(strict_types=1);

namespace App\Services;

final class ImdbParser
{
    public static function parse(string $input): array
    {
        $raw = trim($input);
        $decoded = urldecode($raw);
        preg_match_all('/\b(?:tt|nm|co|ev|ch|ni)\d{2,12}\b/i', $decoded, $matches);
        $ids = array_values(array_unique(array_map('strtolower', $matches[0] ?? [])));
        return [
            'raw' => $raw,
            'ids' => $ids,
            'primary' => $ids[0] ?? null,
            'type' => isset($ids[0]) ? substr($ids[0], 0, 2) : null,
        ];
    }
}
