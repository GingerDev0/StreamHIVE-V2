<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\View;
use App\Models\Repository;
use App\Services\ImportService;

final class AdminController
{
    private Repository $repo;

    public function __construct()
    {
        $this->repo = new Repository();
    }

    public function dashboard(): string
    {
        $this->guard();

        $movies = $this->repo->movies->all();
        $tv = $this->repo->tv->all();
        $people = $this->repo->people->all();
        $allMedia = array_merge(
            array_map(static fn(array $item): array => $item + ['admin_type' => 'movies'], $movies),
            array_map(static fn(array $item): array => $item + ['admin_type' => 'tv'], $tv)
        );

        usort($allMedia, static fn(array $a, array $b): int => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));

        return View::render('admin/dashboard', [
            'title' => 'Admin Dashboard',
            'metaDescription' => 'Private admin dashboard for managing Movie DB content.',
            'robots' => 'noindex, nofollow',
            'movieCount' => count($movies),
            'tvCount' => count($tv),
            'peopleCount' => count($people),
            'prefetchedCount' => count(array_filter($allMedia, static fn(array $item): bool => ($item['import_status'] ?? '') === 'prefetched')),
            'missingRatingCount' => count(array_filter($allMedia, static fn(array $item): bool => trim((string)($item['age_rating'] ?? '')) === '')),
            'recentItems' => array_slice($allMedia, 0, 8),
            'storageStats' => $this->storageStats(),
        ]);
    }

    public function import(): string
    {
        $this->guard();

        $message = null;
        $error = null;
        $record = null;
        $input = trim((string)($_POST['input'] ?? ''));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $record = (new ImportService())->importInput($input);
                $message = 'Imported “' . ($record['title'] ?? $record['name'] ?? $record['id'] ?? 'item') . '” successfully.';
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return View::render('admin/import', compact('message', 'error', 'record', 'input') + [
            'title' => 'Import Media',
            'metaDescription' => 'Private import tool for Movie DB content.',
            'robots' => 'noindex, nofollow',
        ]);
    }

    public function manage(array $params): string
    {
        $this->guard();

        $type = (string)($params['type'] ?? 'movies');
        if (!in_array($type, ['movies', 'tv', 'people'], true)) {
            $type = 'movies';
        }

        $q = trim((string)($_GET['q'] ?? ''));
        $status = (string)($_GET['status'] ?? 'all');
        $sort = (string)($_GET['sort'] ?? 'updated_desc');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 24;

        $items = $this->repo->store($type)->all();

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $items = array_values(array_filter($items, static function (array $item) use ($needle): bool {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    (string)($item['id'] ?? ''),
                    (string)($item['title'] ?? $item['name'] ?? ''),
                    (string)($item['slug'] ?? ''),
                    implode(' ', (array)($item['genres'] ?? [])),
                    (string)($item['imdb_id'] ?? ''),
                ])));
                return str_contains($haystack, $needle);
            }));
        }

        if ($status !== 'all') {
            $items = array_values(array_filter($items, static fn(array $item): bool => (string)($item['import_status'] ?? 'full') === $status));
        }

        usort($items, static function (array $a, array $b) use ($sort): int {
            $titleA = (string)($a['title'] ?? $a['name'] ?? '');
            $titleB = (string)($b['title'] ?? $b['name'] ?? '');
            return match ($sort) {
                'title_asc' => strcasecmp($titleA, $titleB),
                'title_desc' => strcasecmp($titleB, $titleA),
                'rating_desc' => ((float)($b['vote_average'] ?? 0)) <=> ((float)($a['vote_average'] ?? 0)),
                'rating_asc' => ((float)($a['vote_average'] ?? 0)) <=> ((float)($b['vote_average'] ?? 0)),
                'date_desc' => strcmp((string)($b['release_date'] ?? $b['first_air_date'] ?? ''), (string)($a['release_date'] ?? $a['first_air_date'] ?? '')),
                'date_asc' => strcmp((string)($a['release_date'] ?? $a['first_air_date'] ?? ''), (string)($b['release_date'] ?? $b['first_air_date'] ?? '')),
                default => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')),
            };
        });

        $total = count($items);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $items = array_slice($items, ($page - 1) * $perPage, $perPage);

        return View::render('admin/manage', compact('type', 'items', 'q', 'status', 'sort', 'page', 'pages', 'total', 'perPage') + [
            'title' => 'Manage ' . ucfirst($type),
            'metaDescription' => 'Private management page for Movie DB content.',
            'robots' => 'noindex, nofollow',
        ]);
    }

    public function delete(array $params): string
    {
        $this->guard();
        $type = (string)($params['type'] ?? 'movies');
        $this->repo->store($type)->delete($params['id']);
        header('Location: /admin/manage/' . $type . '?token=' . urlencode($_GET['token'] ?? ''));
        return '';
    }

    private function guard(): void
    {
        $token = Config::get('ADMIN_TOKEN', 'change-this-token');
        if ($token && ($_GET['token'] ?? $_POST['token'] ?? '') !== $token) {
            http_response_code(403);
            exit('Forbidden: add ?token=YOUR_ADMIN_TOKEN');
        }
    }

    private function storageStats(): array
    {
        $stats = [];
        foreach (['movies', 'tv', 'people'] as $bucket) {
            $dir = storage_path($bucket);
            $files = glob($dir . '/*.json') ?: [];
            $rows = 0;
            foreach ($files as $file) {
                $json = json_decode((string)file_get_contents($file), true);
                $rows += is_array($json) ? count($json) : 0;
            }
            $stats[$bucket] = [
                'files' => count($files),
                'rows' => $rows,
                'capacity' => max(100, count($files) * 100),
                'percent' => count($files) > 0 ? min(100, (int)round(($rows / (count($files) * 100)) * 100)) : 0,
            ];
        }
        return $stats;
    }
}
