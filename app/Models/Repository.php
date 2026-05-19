<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\SqliteStore;

final class Repository
{
    public SqliteStore $movies;
    public SqliteStore $tv;
    public SqliteStore $people;

    public function __construct()
    {
        $this->movies = new SqliteStore('movies');
        $this->tv = new SqliteStore('tv');
        $this->people = new SqliteStore('people');
    }

    public function bySlug(string $type, string $slug): ?array
    {
        return $this->store($type)->findBy('slug', $slug);
    }

    public function store(string $type): SqliteStore
    {
        return match ($type) {
            'movie', 'movies' => $this->movies,
            'tv' => $this->tv,
            'person', 'people', 'actors' => $this->people,
            default => throw new \InvalidArgumentException('Unknown type: ' . $type),
        };
    }
}
