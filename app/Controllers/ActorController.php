<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Repository;
use App\Services\ImportService;

final class ActorController
{
    public function show(array $params): string
    {
        $repo = new Repository();
        $importer = new ImportService($repo);
        $actor = $repo->bySlug('people', $params['slug']);

        if (!$actor) {
            $tmdbId = isset($_GET['tmdb_id']) && ctype_digit((string)$_GET['tmdb_id']) ? (int)$_GET['tmdb_id'] : null;
            try {
                $actor = $importer->importPersonFromSlug($params['slug'], $tmdbId);
            } catch (\Throwable) {
                $actor = null;
            }
        }

        if (!$actor) {
            http_response_code(404);
            return View::render('pages/404', [
                'title' => 'Actor not found',
                'robots' => 'noindex, follow',
                'metaDescription' => 'The actor page you requested could not be found.',
            ]);
        }

        $actor = $importer->ensureFull($actor, 'person');
        return View::render('pages/actor', [
            'title' => ($actor['name'] ?? 'Actor') . ' | Filmography',
            'metaDescription' => meta_excerpt(($actor['biography'] ?? '') ?: ('Browse movies and TV shows featuring ' . ($actor['name'] ?? 'this actor') . '.')),
            'ogTitle' => ($actor['name'] ?? 'Actor') . ' | Movie DB',
            'ogDescription' => meta_excerpt(($actor['biography'] ?? '') ?: ('Movies and TV shows featuring ' . ($actor['name'] ?? 'this actor') . '.')),
            'ogType' => 'profile',
            'ogImage' => meta_image($actor['profile_path'] ?? null, 'w500'),
            'canonicalUrl' => absolute_url('actors/' . ($actor['slug'] ?? '')),
            'actor' => $actor,
        ]);
    }
}
