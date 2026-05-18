<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\JsonShardStore;

final class Repository
{
    public JsonShardStore $movies;
    public JsonShardStore $tv;
    public JsonShardStore $people;

    public function __construct()
    {
        $this->movies = new JsonShardStore(storage_path('movies'));
        $this->tv = new JsonShardStore(storage_path('tv'));
        $this->people = new JsonShardStore(storage_path('people'));
    }

    public function bySlug(string $type, string $slug): ?array
    {
        return $this->store($type)->findBy('slug', $slug);
    }

    public function store(string $type): JsonShardStore
    {
        return match ($type) {
            'movie', 'movies' => $this->movies,
            'tv' => $this->tv,
            'person', 'people', 'actors' => $this->people,
            default => throw new \InvalidArgumentException('Unknown type: ' . $type),
        };
    }
}
