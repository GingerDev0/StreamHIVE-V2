<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Repository;
use App\Services\TmdbClient;
use App\Services\ImportService;

final class MediaController
{
    private Repository $repo;
    private ImportService $importer;

    public function __construct()
    {
        $this->repo = new Repository();
        $this->importer = new ImportService($this->repo);
    }

    public function movie(array $params): string
    {
        $movie = $this->repo->bySlug('movie', $params['slug']);
        if (!$movie) $movie = $this->autoImport('movie', $params['slug']);
        if (!$movie) return $this->notFound();
        $movie = $this->importer->ensureFull($movie, 'movie');
        if (!is_released_media($movie)) return $this->notFound();
        return View::render('pages/movie', [
            'title' => ($movie['title'] ?? 'Movie') . ' | Watch Movie',
            'metaDescription' => meta_excerpt($movie['overview'] ?? ('Watch ' . ($movie['title'] ?? 'this movie') . ' with cast, ratings, genres, and recommendations.')),
            'ogTitle' => ($movie['title'] ?? 'Movie') . ' | Movie DB',
            'ogDescription' => meta_excerpt($movie['overview'] ?? ''),
            'ogType' => 'video.movie',
            'ogImage' => meta_image($movie['backdrop_path'] ?? ($movie['poster_path'] ?? null)),
            'canonicalUrl' => absolute_url('movies/' . ($movie['slug'] ?? '')),
            'item' => $movie,
            'related' => $this->relatedItems($movie, 'movie'),
        ]);
    }

    public function tv(array $params): string
    {
        $tv = $this->repo->bySlug('tv', $params['slug']);
        if (!$tv) $tv = $this->autoImport('tv', $params['slug']);
        if (!$tv) return $this->notFound();
        $tv = $this->importer->ensureFull($tv, 'tv');
        if (!is_released_media($tv)) return $this->notFound();
        return View::render('pages/tv', [
            'title' => ($tv['title'] ?? 'TV Show') . ' | TV Show',
            'metaDescription' => meta_excerpt($tv['overview'] ?? ('Explore episodes, seasons, cast, ratings, and recommendations for ' . ($tv['title'] ?? 'this TV show') . '.')),
            'ogTitle' => ($tv['title'] ?? 'TV Show') . ' | Movie DB',
            'ogDescription' => meta_excerpt($tv['overview'] ?? ''),
            'ogType' => 'video.tv_show',
            'ogImage' => meta_image($tv['backdrop_path'] ?? ($tv['poster_path'] ?? null)),
            'canonicalUrl' => absolute_url('tv/' . ($tv['slug'] ?? '')),
            'item' => $tv,
            'related' => $this->relatedItems($tv, 'tv'),
        ]);
    }

    public function season(array $params): string
    {
        $tv = $this->repo->bySlug('tv', $params['slug']);
        if (!$tv) $tv = $this->autoImport('tv', $params['slug']);
        if (!$tv) return $this->notFound();
        $tv = $this->importer->ensureFull($tv, 'tv');
        if (!is_released_media($tv)) return $this->notFound();
        $seasonNumber = (int)$params['season'];
        if ($seasonNumber < 1) return $this->notFound();

        try {
            $season = (new TmdbClient())->season((int)$tv['tmdb_id'], $seasonNumber);
        } catch (\Throwable) {
            return $this->notFound();
        }
        if (is_future_date((string)($season['air_date'] ?? ''))) return $this->notFound();
        $season['episodes'] = array_values(array_filter($season['episodes'] ?? [], static fn(array $episode): bool => !is_future_date((string)($episode['air_date'] ?? ''))));

        return View::render('pages/season', [
            'title' => $tv['title'] . ' - Season ' . $seasonNumber,
            'metaDescription' => meta_excerpt('Browse every episode from ' . $tv['title'] . ' season ' . $seasonNumber . '.'),
            'ogTitle' => $tv['title'] . ' - Season ' . $seasonNumber . ' | Movie DB',
            'ogDescription' => meta_excerpt($season['overview'] ?? ($tv['overview'] ?? '')),
            'ogType' => 'video.tv_show',
            'ogImage' => meta_image($season['poster_path'] ?? ($tv['backdrop_path'] ?? ($tv['poster_path'] ?? null))),
            'canonicalUrl' => absolute_url('tv/' . ($tv['slug'] ?? '') . '/s' . str_pad((string)$seasonNumber, 2, '0', STR_PAD_LEFT)),
            'show' => $tv,
            'season' => $season,
            'seasonNumber' => $seasonNumber,
            'related' => $this->relatedItems($tv, 'tv'),
        ]);
    }

    public function episode(array $params): string
    {
        $tv = $this->repo->bySlug('tv', $params['slug']);
        if (!$tv) $tv = $this->autoImport('tv', $params['slug']);
        if (!$tv) return $this->notFound();
        $tv = $this->importer->ensureFull($tv, 'tv');
        if (!is_released_media($tv)) return $this->notFound();

        $seasonNumber = (int)$params['season'];
        $episodeNumber = (int)$params['episode'];
        $tmdb = new TmdbClient();

        try {
            $episode = $tmdb->episode((int)$tv['tmdb_id'], $seasonNumber, $episodeNumber);
        } catch (\Throwable) {
            return $this->notFound();
        }
        if (is_future_date((string)($episode['air_date'] ?? ''))) return $this->notFound();

        return View::render('pages/episode', [
            'title' => $tv['title'] . ' S' . $params['season'] . 'E' . $params['episode'] . ' - ' . ($episode['name'] ?? 'Episode'),
            'metaDescription' => meta_excerpt($episode['overview'] ?? ('Watch ' . $tv['title'] . ' season ' . $seasonNumber . ', episode ' . $episodeNumber . '.')),
            'ogTitle' => $tv['title'] . ' S' . str_pad((string)$seasonNumber, 2, '0', STR_PAD_LEFT) . 'E' . str_pad((string)$episodeNumber, 2, '0', STR_PAD_LEFT) . ' - ' . ($episode['name'] ?? 'Episode'),
            'ogDescription' => meta_excerpt($episode['overview'] ?? ($tv['overview'] ?? '')),
            'ogType' => 'video.episode',
            'ogImage' => meta_image($episode['still_path'] ?? ($tv['backdrop_path'] ?? ($tv['poster_path'] ?? null))),
            'canonicalUrl' => absolute_url('tv/' . ($tv['slug'] ?? '') . '/s' . str_pad((string)$seasonNumber, 2, '0', STR_PAD_LEFT) . '/e' . str_pad((string)$episodeNumber, 2, '0', STR_PAD_LEFT)),
            'show' => $tv,
            'episode' => $episode,
            'season' => $seasonNumber,
            'nextEpisode' => $this->nextEpisode($tmdb, $tv, $seasonNumber, $episodeNumber),
            'related' => $this->relatedItems($tv, 'tv'),
        ]);
    }


    public function comingThisYear(array $params): string
    {
        $year = (int)(new \DateTimeImmutable('today'))->format('Y');
        $this->prefetchComingThisYear($year);

        $movies = $this->comingItems($this->repo->movies->all(), 'movie', $year);
        $tvShows = $this->comingItems($this->repo->tv->all(), 'tv', $year);

        return View::render('pages/coming', [
            'title' => 'Coming This Year',
            'metaDescription' => 'Browse movies and TV shows coming later this year, grouped into tabs with quick pagination.',
            'ogTitle' => 'Coming This Year | Movie DB',
            'ogDescription' => 'Upcoming movies and TV shows coming this year.',
            'canonicalUrl' => absolute_url('coming-this-year'),
            'year' => $year,
            'movies' => $movies,
            'tvShows' => $tvShows,
        ]);
    }

    public function movies(array $params): string
    {
        return $this->listing('movie');
    }

    public function tvShows(array $params): string
    {
        return $this->listing('tv');
    }

    public function search(array $params): string
    {
        $pathQuery = isset($params['query']) ? str_replace('+', ' ', urldecode($params['query'])) : '';
        $query = trim((string)($_GET['q'] ?? $pathQuery));
        $requestedType = (string)($_GET['type'] ?? 'all');
        $type = in_array($requestedType, ['all','movie','tv','person'], true) ? $requestedType : 'all';
        $genre = trim((string)($_GET['genre'] ?? ''));
        $rating = trim((string)($_GET['rating'] ?? ''));
        $year = trim((string)($_GET['year'] ?? ''));
        $sort = (string)($_GET['sort'] ?? 'title_asc');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(48, max(6, (int)($_GET['per_page'] ?? 24)));

        $this->prefetchForSearch($query, $type, $page, $perPage);

        $items = [];
        if ($type === 'all' || $type === 'movie') $items = array_merge($items, $this->repo->movies->all());
        if ($type === 'all' || $type === 'tv') $items = array_merge($items, $this->repo->tv->all());
        if ($type === 'person') $items = array_merge($items, $this->repo->people->all());

        $items = $this->filterItems($items, $query, $genre, $rating, $year);
        $items = $this->sortItems($items, $sort);
        $pagination = $this->paginate($items, $page, $perPage);

        return View::render('pages/search', [
            'title' => $query ? 'Search: ' . $query : 'Discover Movies and TV',
            'metaDescription' => $query ? meta_excerpt('Search results for ' . $query . ' across movies, TV shows, and actors.') : 'Discover movies and TV shows by title, genre, age rating, year, and sort order.',
            'ogTitle' => $query ? 'Search: ' . $query . ' | Movie DB' : 'Discover Movies and TV | Movie DB',
            'ogDescription' => $query ? meta_excerpt('Search results for ' . $query . ' on Movie DB.') : 'Find movies and TV shows with advanced filters.',
            'canonicalUrl' => absolute_url('s' . (!empty($_SERVER['QUERY_STRING'] ?? '') ? '?' . (string)$_SERVER['QUERY_STRING'] : '')),
            'items' => $pagination['items'],
            'total' => $pagination['total'],
            'page' => $pagination['page'],
            'pages' => $pagination['pages'],
            'perPage' => $perPage,
            'query' => $query,
            'type' => $type,
            'genre' => $genre,
            'rating' => $rating,
            'year' => $year,
            'sort' => $sort,
            'genres' => $this->availableGenres(),
            'ratings' => $this->availableRatings(),
        ]);
    }

    private function listing(string $type): string
    {
        $genre = trim((string)($_GET['genre'] ?? ''));
        $rating = trim((string)($_GET['rating'] ?? ''));
        $year = trim((string)($_GET['year'] ?? ''));
        $sort = (string)($_GET['sort'] ?? 'title_asc');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(48, max(6, (int)($_GET['per_page'] ?? 24)));

        $this->prefetchForListing($type, $page, $perPage);

        $items = $type === 'movie' ? $this->repo->movies->all() : $this->repo->tv->all();
        $items = $this->filterItems($items, '', $genre, $rating, $year);
        $items = $this->sortItems($items, $sort);
        $pagination = $this->paginate($items, $page, $perPage);

        return View::render('pages/listing', [
            'title' => $type === 'movie' ? 'All Movies' : 'All TV Shows',
            'metaDescription' => $type === 'movie' ? 'Browse every saved movie with filters, sorting, and pagination.' : 'Browse every saved TV show with filters, sorting, and pagination.',
            'ogTitle' => $type === 'movie' ? 'All Movies | Movie DB' : 'All TV Shows | Movie DB',
            'ogDescription' => $type === 'movie' ? 'Browse all movies on Movie DB.' : 'Browse all TV shows on Movie DB.',
            'canonicalUrl' => absolute_url($type === 'movie' ? 'movies' : 'tv'),
            'heading' => $type === 'movie' ? 'All Movies' : 'All TV Shows',
            'items' => $pagination['items'],
            'total' => $pagination['total'],
            'page' => $pagination['page'],
            'pages' => $pagination['pages'],
            'perPage' => $perPage,
            'type' => $type,
            'genre' => $genre,
            'rating' => $rating,
            'year' => $year,
            'sort' => $sort,
            'genres' => $this->availableGenres($type),
            'ratings' => $this->availableRatings($type),
        ]);
    }



    private function nextEpisode(TmdbClient $tmdb, array $tv, int $seasonNumber, int $episodeNumber): ?array
    {
        $seriesId = (int)($tv['tmdb_id'] ?? 0);
        if ($seriesId < 1 || $seasonNumber < 1 || $episodeNumber < 1) return null;

        $seasonNumbers = [$seasonNumber];
        foreach (($tv['seasons'] ?? []) as $season) {
            $number = (int)($season['season_number'] ?? 0);
            if ($number > $seasonNumber) $seasonNumbers[] = $number;
        }
        $seasonNumbers = array_values(array_unique($seasonNumbers));
        sort($seasonNumbers, SORT_NUMERIC);

        foreach ($seasonNumbers as $candidateSeason) {
            try {
                $seasonData = $tmdb->season($seriesId, (int)$candidateSeason);
            } catch (\Throwable) {
                continue;
            }

            $episodes = $seasonData['episodes'] ?? [];
            usort($episodes, static fn(array $a, array $b): int => ((int)($a['episode_number'] ?? 0)) <=> ((int)($b['episode_number'] ?? 0)));

            foreach ($episodes as $episode) {
                $number = (int)($episode['episode_number'] ?? 0);
                if ($number < 1) continue;
                if (is_future_date((string)($episode['air_date'] ?? ''))) continue;
                if ((int)$candidateSeason === $seasonNumber && $number <= $episodeNumber) continue;

                return [
                    'season_number' => (int)$candidateSeason,
                    'episode' => $episode,
                ];
            }
        }

        return null;
    }



    private function prefetchComingThisYear(int $year): void
    {
        $start = max(
            (new \DateTimeImmutable('tomorrow'))->format('Y-m-d'),
            sprintf('%d-01-01', $year)
        );
        $end = sprintf('%d-12-31', $year);

        if ($start > $end) return;

        $tmdb = new TmdbClient();
        try {
            for ($page = 1; $page <= 2; $page++) {
                $movies = $tmdb->comingMoviesThisYear($start, $end, $page);
                $tv = $tmdb->comingTvThisYear($start, $end, $page);
                $this->importer->prefetchResults($movies['results'] ?? [], 'movie', 20);
                $this->importer->prefetchResults($tv['results'] ?? [], 'tv', 20);
            }
        } catch (\Throwable) {
            // Keep the page working from existing local JSON if TMDB is unavailable.
        }
    }

    private function comingItems(array $items, string $type, int $year): array
    {
        $today = new \DateTimeImmutable('today');
        $end = new \DateTimeImmutable(sprintf('%d-12-31', $year));

        $items = array_values(array_filter($items, static function (array $item) use ($type, $today, $end): bool {
            if (($item['media_type'] ?? $type) !== $type) return false;
            $date = media_release_date($item);
            if ($date === '') return false;
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$dt) return false;
            return $dt > $today && $dt <= $end;
        }));

        usort($items, static function (array $a, array $b): int {
            $dateCompare = strcmp(media_release_date($a), media_release_date($b));
            if ($dateCompare !== 0) return $dateCompare;
            return ((float)($b['vote_average'] ?? 0)) <=> ((float)($a['vote_average'] ?? 0));
        });

        return $items;
    }

    private function prefetchForListing(string $type, int $page, int $perPage): void
    {
        try {
            $this->importer->prefetchPopular($type, $page, min(20, $perPage));
        } catch (\Throwable) {
            // Listing pages still work from existing local JSON if TMDB is unavailable.
        }
    }

    private function prefetchForSearch(string $query, string $type, int $page, int $perPage): void
    {
        if ($query === '') return;
        $limit = min(20, $perPage);
        try {
            if ($type !== 'tv' && $type !== 'person') $this->importer->prefetchSearch($query, 'movie', $page, $limit);
            if ($type !== 'movie' && $type !== 'person') $this->importer->prefetchSearch($query, 'tv', $page, $limit);
            if ($type === 'person') $this->importer->prefetchSearch($query, 'person', $page, $limit);
        } catch (\Throwable) {
            // Search falls back to existing local JSON if TMDB is unavailable.
        }
    }

    private function relatedItems(array $current, string $type, int $limit = 6): array
    {
        $currentId = (string)($current['id'] ?? '');
        $currentTmdbId = (string)($current['tmdb_id'] ?? '');
        $currentSlug = (string)($current['slug'] ?? '');
        $currentGenres = array_map('strtolower', $current['genres'] ?? []);
        $items = $type === 'movie' ? $this->repo->movies->all() : $this->repo->tv->all();

        $scored = [];
        foreach ($items as $item) {
            if (is_future_date(media_release_date($item))) continue;
            $itemId = (string)($item['id'] ?? '');
            $itemTmdbId = (string)($item['tmdb_id'] ?? '');
            $itemSlug = (string)($item['slug'] ?? '');
            if (($currentId !== '' && $itemId === $currentId) || ($currentTmdbId !== '' && $itemTmdbId === $currentTmdbId) || ($currentSlug !== '' && $itemSlug === $currentSlug)) {
                continue;
            }

            $itemGenres = array_map('strtolower', $item['genres'] ?? []);
            $sharedGenres = count(array_intersect($currentGenres, $itemGenres));
            $score = ($sharedGenres * 10) + ((float)($item['vote_average'] ?? 0));
            if ($sharedGenres === 0 && !empty($currentGenres)) {
                $score = (float)($item['vote_average'] ?? 0);
            }

            $item['_related_score'] = $score;
            $scored[] = $item;
        }

        usort($scored, static function (array $a, array $b): int {
            $score = ((float)($b['_related_score'] ?? 0)) <=> ((float)($a['_related_score'] ?? 0));
            if ($score !== 0) return $score;
            return strcmp((string)($b['release_date'] ?? ''), (string)($a['release_date'] ?? ''));
        });

        return array_slice($scored, 0, $limit);
    }

    private function filterItems(array $items, string $query = '', string $genre = '', string $rating = '', string $year = ''): array
    {
        $query = strtolower(trim($query));
        return array_values(array_filter($items, function (array $item) use ($query, $genre, $rating, $year): bool {
            $isPerson = ($item['media_type'] ?? '') === 'person' || isset($item['profile_path']);
            if (!$isPerson && is_future_date(media_release_date($item))) return false;
            if ($query !== '') {
                $knownFor = [];
                foreach (($item['known_for'] ?? []) as $credit) $knownFor[] = (string)($credit['title'] ?? '');
                foreach (($item['credits'] ?? []) as $credit) $knownFor[] = (string)($credit['title'] ?? '');
                $haystack = strtolower(implode(' ', array_filter([
                    (string)($item['title'] ?? ''),
                    (string)($item['name'] ?? ''),
                    (string)($item['overview'] ?? ''),
                    (string)($item['biography'] ?? ''),
                    (string)($item['known_for_department'] ?? ''),
                    implode(' ', $item['genres'] ?? []),
                    implode(' ', $knownFor),
                ])));
                if (!str_contains($haystack, $query)) return false;
            }
            if (!$isPerson && $genre !== '' && !in_array($genre, $item['genres'] ?? [], true)) return false;
            if (!$isPerson && $rating !== '' && (string)($item['age_rating'] ?? 'NR') !== $rating) return false;
            if (!$isPerson && $year !== '') {
                $date = (string)($item['release_date'] ?? $item['first_air_date'] ?? '');
                if (substr($date, 0, 4) !== $year) return false;
            }
            if ($isPerson && ($genre !== '' || $rating !== '' || $year !== '')) return false;
            return true;
        }));
    }

    private function sortItems(array $items, string $sort): array
    {
        usort($items, function (array $a, array $b) use ($sort): int {
            return match ($sort) {
                'title_desc' => strnatcasecmp($b['title'] ?? $b['name'] ?? '', $a['title'] ?? $a['name'] ?? ''),
                'date_asc' => strcmp((string)($a['release_date'] ?? ''), (string)($b['release_date'] ?? '')),
                'date_desc' => strcmp((string)($b['release_date'] ?? ''), (string)($a['release_date'] ?? '')),
                'rating_asc' => ((float)($a['vote_average'] ?? 0)) <=> ((float)($b['vote_average'] ?? 0)),
                'rating_desc' => ((float)($b['vote_average'] ?? 0)) <=> ((float)($a['vote_average'] ?? 0)),
                'updated_desc' => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')),
                default => strnatcasecmp($a['title'] ?? $a['name'] ?? '', $b['title'] ?? $b['name'] ?? ''),
            };
        });
        return $items;
    }

    private function paginate(array $items, int $page, int $perPage): array
    {
        $total = count($items);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        return [
            'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    private function availableGenres(?string $type = null): array
    {
        $items = $type === 'movie' ? $this->repo->movies->all() : ($type === 'tv' ? $this->repo->tv->all() : array_merge($this->repo->movies->all(), $this->repo->tv->all()));
        $genres = [];
        foreach ($items as $item) foreach (($item['genres'] ?? []) as $g) if ($g !== '') $genres[$g] = $g;
        natcasesort($genres);
        return array_values($genres);
    }

    private function availableRatings(?string $type = null): array
    {
        $items = $type === 'movie' ? $this->repo->movies->all() : ($type === 'tv' ? $this->repo->tv->all() : array_merge($this->repo->movies->all(), $this->repo->tv->all()));
        $ratings = [];
        foreach ($items as $item) {
            $r = (string)($item['age_rating'] ?? 'NR');
            if ($r !== '') $ratings[$r] = $r;
        }
        natcasesort($ratings);
        return array_values($ratings);
    }

    private function autoImport(string $type, string $slug): ?array
    {
        $tmdbId = isset($_GET['tmdb_id']) && ctype_digit((string)$_GET['tmdb_id']) ? (int)$_GET['tmdb_id'] : null;

        try {
            return $type === 'movie'
                ? $this->importer->importMovieFromSlug($slug, $tmdbId)
                : $this->importer->importTvFromSlug($slug, $tmdbId);
        } catch (\Throwable) {
            return null;
        }
    }

    private function notFound(): string { http_response_code(404); return View::render('pages/404', [
        'title' => 'Not found',
        'metaDescription' => 'The page you requested could not be found.',
        'robots' => 'noindex, follow',
    ]); }
}
