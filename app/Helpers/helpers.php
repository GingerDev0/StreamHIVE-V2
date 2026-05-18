<?php

declare(strict_types=1);

function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function asset(string $path): string { return '/assets/' . ltrim($path, '/'); }
function url(string $path): string { return '/' . ltrim($path, '/'); }

function absolute_url(string $path = ''): string {
    $path = trim($path);
    if ($path !== '' && preg_match('~^https?://~i', $path)) return $path;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if ($path === '') {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return $scheme . '://' . $host . $uri;
    }
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}
function current_url(): string { return absolute_url(''); }
function meta_excerpt(?string $text, int $length = 160): string {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$text)) ?: '');
    if ($text === '') return '';
    if (function_exists('mb_strlen') && mb_strlen($text) > $length) {
        return rtrim((string)mb_substr($text, 0, $length - 1)) . '…';
    }
    if (strlen($text) > $length) return rtrim(substr($text, 0, $length - 1)) . '…';
    return $text;
}
function meta_image(?string $path, string $size = 'w1280'): string { return absolute_url(tmdb_img($path, $size)); }
function tmdb_img(?string $path, string $size = 'w500'): string {
    return $path ? "https://image.tmdb.org/t/p/{$size}{$path}" : '/assets/img/placeholder.svg';
}
function slugify(string $text): string {
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim((string)$text, '-');
    $text = strtolower($text);
    return $text ?: 'item';
}
function media_url(array $item): string {
    if (($item['media_type'] ?? '') === 'tv') return url('tv/' . $item['slug']);
    return url('movies/' . $item['slug']);
}
function actor_url(array $person): string { return url('actors/' . $person['slug']); }


function media_release_date(array $item): string {
    return trim((string)($item['release_date'] ?? $item['first_air_date'] ?? $item['air_date'] ?? ''));
}
function is_future_date(?string $date): bool {
    $date = trim((string)$date);
    if ($date === '') return false;
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$dt) return false;
    $today = new DateTimeImmutable('today');
    return $dt > $today;
}
function is_released_media(array $item): bool {
    return !is_future_date(media_release_date($item));
}

function format_date(?string $date): string {
    $date = trim((string)$date);
    if ($date === '') return '';
    $dt = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$dt) return $date;
    $day = (int)$dt->format('j');
    if ($day >= 11 && $day <= 13) {
        $suffix = 'th';
    } else {
        $suffix = match ($day % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
    return $day . $suffix . ' ' . $dt->format('F Y');
}
function format_year(?string $date): string {
    $date = trim((string)$date);
    if ($date === '') return '';
    $dt = DateTime::createFromFormat('!Y-m-d', $date);
    return $dt ? $dt->format('Y') : substr($date, 0, 4);
}

function search_url(array $params = []): string {
    $query = http_build_query(array_filter($params, static fn($v) => $v !== null && $v !== ''));
    return url('s') . ($query ? '?' . $query : '');
}
function genre_url(string $genre, ?string $type = null): string {
    return search_url(['genre' => $genre, 'type' => $type]);
}
function genre_links(array $genres, ?string $type = null, int $limit = 0, string $class = 'genre-link'): string {
    $genres = array_values(array_filter(array_map('strval', $genres), static fn($g) => trim($g) !== ''));
    if ($limit > 0) $genres = array_slice($genres, 0, $limit);
    if (!$genres) return '<span class="text-white-50">Genre TBA</span>';
    $links = [];
    foreach ($genres as $genre) {
        $links[] = '<a class="' . e($class) . '" href="' . e(genre_url($genre, $type)) . '">' . e($genre) . '</a>';
    }
    return implode('<span class="genre-separator">, </span>', $links);
}


function media_storage_payload(array $item, string $type, ?string $href = null, ?string $titleOverride = null, ?string $metaOverride = null, ?string $posterOverride = null): string {
    $title = $titleOverride ?: (string)($item['title'] ?? $item['name'] ?? 'Untitled');
    $date = (string)($item['release_date'] ?? $item['first_air_date'] ?? '');
    $year = format_year($date);
    $slug = (string)($item['slug'] ?? slugify($title));
    $href = $href ?: ($type === 'tv' ? url('tv/' . $slug) : url('movies/' . $slug));
    $poster = $posterOverride ?: tmdb_img($item['poster_path'] ?? null);
    $meta = $metaOverride ?: trim(implode(' · ', array_filter([
        $type === 'tv' ? 'TV Show' : 'Movie',
        $year,
        !empty($item['age_rating']) ? (string)$item['age_rating'] : null,
    ])));
    $payload = [
        'type' => $type,
        'tmdb_id' => $item['tmdb_id'] ?? $item['id'] ?? null,
        'slug' => $slug,
        'title' => $title,
        'url' => $href,
        'poster' => $poster,
        'backdrop' => tmdb_img($item['backdrop_path'] ?? ($item['poster_path'] ?? null), 'w780'),
        'year' => $year,
        'rating' => round((float)($item['vote_average'] ?? 0), 1),
        'meta' => $meta,
    ];
    return htmlspecialchars((string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
}
