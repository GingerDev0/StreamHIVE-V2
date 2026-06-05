<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Repository;
use App\Services\ImportService;
use App\Services\TmdbClient;
use App\Services\MysqliStore;
use App\Services\SiteSettings;

final class HomeController
{
    public function index(): string
    {
        $tmdb = new TmdbClient();
        $importer = new ImportService(new Repository(), $tmdb);
        $safe = static function (callable $cb): array {
            try { return $cb(); }
            catch (\Throwable) { return []; }
        };

        $moviesRecent = array_values(array_filter($safe(fn() => $tmdb->recentMovies()['results'] ?? []), 'is_released_media'));
        $moviesTrending = array_values(array_filter($safe(fn() => $tmdb->trending('movie')['results'] ?? []), 'is_released_media'));
        $moviesTopRated = array_values(array_filter($safe(fn() => $tmdb->topRatedMovies()['results'] ?? []), 'is_released_media'));
        $tvRecent = array_values(array_filter($safe(fn() => $tmdb->recentTv()['results'] ?? []), 'is_released_media'));
        $tvTrending = array_values(array_filter($safe(fn() => $tmdb->trending('tv')['results'] ?? []), 'is_released_media'));

        // Eagerly save everything displayed on the homepage, so cards point to permanent local slugs before users click.
        $safe(fn() => ['count' => $importer->prefetchResults(array_slice($moviesRecent, 0, 12), 'movie', 12)]);
        $safe(fn() => ['count' => $importer->prefetchResults(array_slice($moviesTrending, 0, 12), 'movie', 12)]);
        $safe(fn() => ['count' => $importer->prefetchResults(array_slice($tvRecent, 0, 12), 'tv', 12)]);
        $safe(fn() => ['count' => $importer->prefetchResults(array_slice($tvTrending, 0, 12), 'tv', 12)]);

        $repo = new Repository();
        $moviesRecent = $this->hydrateFromLocal($moviesRecent, 'movie', $repo);
        $moviesTrending = $this->hydrateFromLocal($moviesTrending, 'movie', $repo);
        $moviesTopRated = $this->hydrateFromLocal($moviesTopRated, 'movie', $repo);
        $tvRecent = $this->hydrateFromLocal($tvRecent, 'tv', $repo);
        $tvTrending = $this->hydrateFromLocal($tvTrending, 'tv', $repo);

        $heroMovies = $safe(fn() => MysqliStore::heroCarouselMovies(10));
        if (count($heroMovies) < 10) {
            $heroCandidates = $this->discoverHeroCandidates($tmdb, $safe);
            if ($heroCandidates) {
                $safe(fn() => ['count' => $importer->prefetchResults($heroCandidates, 'movie', count($heroCandidates))]);
                $heroCandidates = $this->hydrateFromLocal($heroCandidates, 'movie', $repo);
                $heroMovies = $safe(fn() => MysqliStore::heroCarouselMovies(10));
            }
        }

        if (count($heroMovies) < 10) {
            $heroMovies = $this->mergeHeroMovies(
                $heroMovies,
                $this->randomMoviesForHero($heroCandidates ?? [], $moviesTopRated, $moviesTrending, $moviesRecent)
            );
        }

        return View::render('pages/home', compact('moviesRecent','moviesTrending','tvRecent','tvTrending','heroMovies') + [
            'title' => 'StreamHIVE',
            'metaDescription' => 'Explore trending movies and TV shows with posters, cast, episodes, ratings, genres, bookmarks, and instant playback.',
            'ogTitle' => 'StreamHIVE | Trending Movies and TV Shows',
            'ogDescription' => 'Discover trending movies and TV shows in a bold cinematic interface.',
            'canonicalUrl' => absolute_url('/'),
            'homeAlertSettings' => SiteSettings::all(),
        ]);
    }

    private function randomMoviesForHero(array ...$groups): array
    {
        $seen = [];
        $movies = [];

        foreach ($groups as $group) {
            foreach ($group as $item) {
                $id = (int)($item['tmdb_id'] ?? $item['id'] ?? 0);
                if ($id <= 0 || isset($seen[$id])) continue;

                // Carousel-only rule: skip movies without a real poster here,
                // but do not remove them from normal listings/search/details.
                if (!$this->hasUsablePoster($item)) continue;
                if (!$this->hasMinimumRating($item, 8.0)) continue;
                if (!$this->isEnglishMovie($item)) continue;
                if (!$this->isWithinLastTenYears($item)) continue;

                $seen[$id] = true;
                $item['media_type'] = 'movie';
                $movies[] = $item;
            }
        }

        shuffle($movies);
        return array_slice($movies, 0, 10);
    }

    private function discoverHeroCandidates(TmdbClient $tmdb, callable $safe): array
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
        $cutoff = $today->modify('-10 years')->format('Y-m-d');
        $endDate = $today->format('Y-m-d');
        $groups = [];

        for ($page = 1; $page <= 3; $page++) {
            $results = $safe(fn() => $tmdb->discoverHeroMovies($cutoff, $endDate, $page)['results'] ?? []);
            if ($results) {
                $groups[] = $results;
            }
        }

        return $groups ? $this->randomMoviesForHero(...$groups) : [];
    }

    private function mergeHeroMovies(array $primary, array $fallback): array
    {
        $seen = [];
        $merged = [];

        foreach ([$primary, $fallback] as $group) {
            foreach ($group as $item) {
                $id = (string)($item['tmdb_id'] ?? $item['id'] ?? '');
                if ($id === '' || isset($seen[$id])) continue;

                $seen[$id] = true;
                $merged[] = $item;
                if (count($merged) >= 10) {
                    return $merged;
                }
            }
        }

        return $merged;
    }

    private function hasUsablePoster(array $item): bool
    {
        $poster = trim((string)($item['poster_path'] ?? ''));
        if ($poster === '') return false;

        return !str_contains($poster, 'placeholder.jpg')
            && !str_contains($poster, 'placeholder.svg');
    }

    private function hasMinimumRating(array $item, float $minimum): bool
    {
        $rating = (float)($item['vote_average'] ?? $item['rating'] ?? 0);
        return $rating >= $minimum;
    }

    private function isEnglishMovie(array $item): bool
    {
        return (string)($item['original_language'] ?? '') === 'en';
    }

    private function isWithinLastTenYears(array $item): bool
    {
        $releaseDate = (string)($item['release_date'] ?? '');
        if ($releaseDate === '') return false;

        $today = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
        $cutoff = $today->modify('-10 years')->format('Y-m-d');

        return $releaseDate >= $cutoff && $releaseDate <= $today->format('Y-m-d');
    }

    private function hydrateFromLocal(array $items, string $type, Repository $repo): array
    {
        $store = $type === 'movie' ? $repo->movies : $repo->tv;
        return array_values(array_map(static function (array $item) use ($store): array {
            $id = (int)($item['id'] ?? 0);
            if ($id > 0) {
                $local = $store->find($id);
                if ($local) return $local;
            }
            return $item;
        }, $items));
    }
}
