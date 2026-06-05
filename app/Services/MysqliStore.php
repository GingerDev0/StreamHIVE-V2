<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use mysqli;
use mysqli_result;
use mysqli_stmt;

final class MysqliStore
{
    private static ?mysqli $connection = null;
    private static bool $schemaReady = false;
    private static bool $inTransaction = false;

    public function __construct(private readonly string $bucket)
    {
        self::connection();
    }

    public function all(): array
    {
        $stmt = self::execute(
            'SELECT payload, created_at, updated_at FROM records WHERE bucket = ? ORDER BY updated_at DESC, id DESC',
            [$this->bucket]
        );
        return self::recordsFromResult($stmt->get_result());
    }

    public function find(int|string $id): ?array
    {
        $stmt = self::execute('SELECT payload, created_at, updated_at FROM records WHERE bucket = ? AND id = ? LIMIT 1', [$this->bucket, (string)$id]);
        return self::recordFromResult($stmt->get_result());
    }

    public function findBy(string $field, mixed $value): ?array
    {
        $column = match ($field) {
            'slug' => 'slug',
            'tmdb_id', 'id' => 'id',
            'imdb_id' => 'imdb_id',
            'import_status' => 'import_status',
            default => null,
        };

        if ($column === null) {
            foreach ($this->all() as $record) {
                if (($record[$field] ?? null) == $value) return $record;
            }
            return null;
        }

        $stmt = self::execute("SELECT payload, created_at, updated_at FROM records WHERE bucket = ? AND {$column} = ? LIMIT 1", [$this->bucket, (string)$value]);
        return self::recordFromResult($stmt->get_result());
    }

    public function upsert(array $record): void
    {
        self::upsertRecord($this->bucket, $record);
    }

    public function upsertMany(array $records): int
    {
        $count = 0;
        self::transaction(function () use ($records, &$count): void {
            foreach ($records as $record) {
                if (!is_array($record)) continue;
                self::upsertRecord($this->bucket, $record);
                $count++;
            }
        });
        return $count;
    }

    public static function transaction(callable $callback): mixed
    {
        $db = self::connection();
        $outer = self::$inTransaction;
        if (!$outer) {
            $db->begin_transaction();
            self::$inTransaction = true;
        }

        try {
            $result = $callback();
            if (!$outer) $db->commit();
            return $result;
        } catch (\Throwable $e) {
            if (!$outer) $db->rollback();
            throw $e;
        } finally {
            if (!$outer) self::$inTransaction = false;
        }
    }

    public function idsWithStatus(array $ids, string $status): array
    {
        $ids = array_values(array_filter(array_map('strval', $ids), static fn(string $id): bool => $id !== ''));
        if (!$ids) return [];

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = self::execute(
            "SELECT id FROM records WHERE bucket = ? AND import_status = ? AND id IN ({$placeholders})",
            array_merge([$this->bucket, $status], $ids)
        );
        $found = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $found[(string)$row['id']] = true;
        }
        return $found;
    }

    public function slugForId(int|string $id): ?string
    {
        $stmt = self::execute('SELECT slug FROM records WHERE bucket = ? AND id = ? LIMIT 1', [$this->bucket, (string)$id]);
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? (string)$row['slug'] : null;
    }

    public function slugExists(string $slug, int|string|null $excludeId = null): bool
    {
        $params = [$this->bucket, $slug];
        $sql = 'SELECT COUNT(*) AS total FROM records WHERE bucket = ? AND slug = ?';
        if ($excludeId !== null && (string)$excludeId !== '') {
            $sql .= ' AND id <> ?';
            $params[] = (string)$excludeId;
        }
        $stmt = self::execute($sql, $params);
        return (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0) > 0;
    }

    public function delete(int|string $id): bool
    {
        $stmt = self::execute('DELETE FROM records WHERE bucket = ? AND id = ?', [$this->bucket, (string)$id]);
        return $stmt->affected_rows > 0;
    }

    public function count(): int
    {
        $stmt = self::execute('SELECT COUNT(*) AS total FROM records WHERE bucket = ?', [$this->bucket]);
        return (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    }

    public function countByStatus(string $status): int
    {
        $stmt = self::execute('SELECT COUNT(*) AS total FROM records WHERE bucket = ? AND import_status = ?', [$this->bucket, $status]);
        return (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    }

    public function idsByStatus(string $status, int $limit = 0): array
    {
        $sql = 'SELECT id FROM records WHERE bucket = ? AND import_status = ? ORDER BY updated_at DESC, id ASC';
        $params = [$this->bucket, $status];
        if ($limit > 0) {
            $sql .= ' LIMIT ?';
            $params[] = $limit;
        }
        $stmt = self::execute($sql, $params);
        return array_map(static fn(array $row): string => (string)$row['id'], $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }

    public static function upcomingInYear(string $bucket, int $year): array
    {
        $start = sprintf('%04d-01-01', $year);
        $end = sprintf('%04d-12-31', $year);
        $stmt = self::execute(
            'SELECT payload, created_at, updated_at FROM records WHERE bucket = ? AND release_date BETWEEN ? AND ? ORDER BY release_date ASC, popularity DESC, title ASC',
            [$bucket, $start, $end]
        );
        return self::recordsFromResult($stmt->get_result());
    }

    public function paginated(array $options): array
    {
        return self::queryBuckets([$this->bucket], $options);
    }

    public static function queryBuckets(array $buckets, array $options): array
    {
        $buckets = array_values(array_unique(array_filter(array_map('strval', $buckets))));
        if (!$buckets) return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];

        $page = max(1, (int)($options['page'] ?? 1));
        $perPage = min(96, max(1, (int)($options['per_page'] ?? 24)));
        $offset = ($page - 1) * $perPage;

        $where = ['bucket IN (' . implode(',', array_fill(0, count($buckets), '?')) . ')'];
        $params = $buckets;
        $containsPeople = in_array('people', $buckets, true);
        $mediaOnly = !$containsPeople;

        if ($mediaOnly) {
            $where[] = "(release_date IS NOT NULL AND release_date <> '' AND release_date <= ?)";
            $params[] = gmdate('Y-m-d');
        }

        $query = trim((string)($options['query'] ?? ''));
        if ($query !== '') {
            $where[] = 'search_text LIKE ?';
            $params[] = '%' . strtolower($query) . '%';
        }

        if ($mediaOnly) {
            $genre = trim((string)($options['genre'] ?? ''));
            if ($genre !== '') {
                $where[] = 'genres_text LIKE ?';
                $params[] = '%|' . $genre . '|%';
            }

            $rating = self::ukAgeRating($options['rating'] ?? '');
            if ($rating !== '') {
                $aliases = self::ukAgeRatingAliases($rating);
                $where[] = 'age_rating IN (' . implode(',', array_fill(0, count($aliases), '?')) . ')';
                array_push($params, ...$aliases);
            }

            $userRating = trim((string)($options['user_rating'] ?? ($options['score'] ?? '')));
            if ($userRating !== '' && is_numeric($userRating)) {
                $minimum = max(0.0, min(10.0, floor(((float)$userRating) * 2) / 2));
                if ($minimum >= 10.0) {
                    $where[] = 'COALESCE(vote_average, 0) >= ?';
                    $params[] = 10.0;
                } else {
                    $where[] = '(COALESCE(vote_average, 0) >= ? AND COALESCE(vote_average, 0) < ?)';
                    $params[] = $minimum;
                    $params[] = $minimum + 0.5;
                }
            }

            $year = trim((string)($options['year'] ?? ''));
            if ($year !== '') {
                $where[] = 'release_year = ?';
                $params[] = $year;
            }
        } elseif (
            trim((string)($options['genre'] ?? '')) !== ''
            || trim((string)($options['rating'] ?? '')) !== ''
            || trim((string)($options['user_rating'] ?? ($options['score'] ?? ''))) !== ''
            || trim((string)($options['year'] ?? '')) !== ''
        ) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = self::execute('SELECT COUNT(*) AS total FROM records WHERE ' . $whereSql, $params);
        $total = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $orderBy = self::orderByForSort((string)($options['sort'] ?? 'title_asc'), $containsPeople);
        $stmt = self::execute(
            'SELECT payload, created_at, updated_at FROM records WHERE ' . $whereSql . ' ORDER BY ' . $orderBy . ' LIMIT ? OFFSET ?',
            array_merge($params, [$perPage, $offset])
        );

        return [
            'items' => self::recordsFromResult($stmt->get_result()),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    public static function liveSearch(string $query, int $limit = 6): array
    {
        $query = trim(preg_replace('/\s+/', ' ', $query) ?: '');
        $limit = min(6, max(1, $limit));
        if ($query === '' || (function_exists('mb_strlen') ? mb_strlen($query) : strlen($query)) < 2) return [];

        $today = gmdate('Y-m-d');
        $like = '%' . strtolower($query) . '%';
        $stmt = self::execute(
            "SELECT payload, bucket, import_status, created_at, updated_at,
                    CASE
                        WHEN LOWER(COALESCE(title, name, '')) = ? THEN 0
                        WHEN LOWER(COALESCE(title, name, '')) LIKE ? THEN 1
                        ELSE 2
                    END AS rank_score
             FROM records
             WHERE search_text LIKE ?
               AND (bucket = 'people' OR (release_date IS NOT NULL AND release_date <> '' AND release_date <= ?))
             ORDER BY rank_score ASC, COALESCE(popularity, 0) DESC, COALESCE(vote_average, 0) DESC, updated_at DESC
             LIMIT ?",
            [strtolower($query), strtolower($query) . '%', $like, $today, max($limit * 4, 12)]
        );

        $items = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $record = self::decode((string)$row['payload']);
            if (!$record) continue;
            $bucket = (string)$row['bucket'];
            if ($bucket !== 'people' && !self::recordHasUsablePoster($record)) continue;

            $record['created_at'] ??= $row['created_at'];
            $record['updated_at'] ??= $row['updated_at'];
            $record['_import_status'] = (string)($row['import_status'] ?? '');
            $items[] = $record;
            if (count($items) >= $limit) break;
        }

        return $items;
    }

    public static function randomReleasedFromBucket(string $bucket, int $limit = 10, bool $requirePoster = false, ?float $minimumRating = null): array
    {
        $limit = min(50, max(1, $limit));
        $params = [$bucket, gmdate('Y-m-d')];
        $where = "bucket = ? AND release_date IS NOT NULL AND release_date <> '' AND release_date <= ?";
        if ($minimumRating !== null) {
            $where .= ' AND COALESCE(vote_average, 0) >= ?';
            $params[] = $minimumRating;
        }

        $stmt = self::execute(
            "SELECT payload, created_at, updated_at FROM records WHERE {$where} ORDER BY RAND() LIMIT ?",
            array_merge($params, [$limit * ($requirePoster ? 5 : 1)])
        );

        $items = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $record = self::decode((string)$row['payload']);
            if (!$record) continue;
            if ($requirePoster && !self::recordHasUsablePoster($record)) continue;
            $record['created_at'] ??= $row['created_at'];
            $record['updated_at'] ??= $row['updated_at'];
            $items[] = $record;
            if (count($items) >= $limit) break;
        }
        return $items;
    }

    public static function heroCarouselMovies(int $limit = 10): array
    {
        $limit = min(10, max(1, $limit));
        $today = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
        $cutoff = $today->modify('-10 years')->format('Y-m-d');

        $stmt = self::execute(
            "SELECT payload, created_at, updated_at
             FROM records
             WHERE bucket = 'movies'
               AND release_date IS NOT NULL
               AND release_date <> ''
               AND release_date BETWEEN ? AND ?
               AND CAST(COALESCE(vote_average, 0) AS DECIMAL(4,2)) >= 8.0
             ORDER BY RAND()
             LIMIT ?",
            [$cutoff, $today->format('Y-m-d'), max(100, $limit * 20)]
        );

        $movies = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $payload = self::decode((string)$row['payload']);
            if (!$payload) continue;
            $payload['created_at'] ??= $row['created_at'];
            $payload['updated_at'] ??= $row['updated_at'];
            if (!self::isHeroCarouselMovie($payload, $cutoff, $today->format('Y-m-d'))) continue;
            $movies[] = $payload;
            if (count($movies) >= $limit) break;
        }
        return $movies;
    }

    public function distinctValues(string $field): array
    {
        $column = match ($field) {
            'age_rating' => 'age_rating',
            'release_year', 'year' => 'release_year',
            default => null,
        };
        if ($column === null) return [];

        $stmt = self::execute("SELECT DISTINCT {$column} AS value FROM records WHERE bucket = ? AND {$column} IS NOT NULL AND {$column} <> '' ORDER BY {$column} ASC", [$this->bucket]);
        return array_values(array_filter(array_map(static fn(array $row): string => (string)$row['value'], $stmt->get_result()->fetch_all(MYSQLI_ASSOC))));
    }

    public function distinctGenres(): array
    {
        $stmt = self::execute("SELECT genres_text FROM records WHERE bucket = ? AND genres_text IS NOT NULL AND genres_text <> ''", [$this->bucket]);
        $genres = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            foreach (explode('|', trim((string)$row['genres_text'], '|')) as $genre) {
                $genre = trim($genre);
                if ($genre !== '') $genres[strtolower($genre)] = $genre;
            }
        }
        natcasesort($genres);
        return array_values($genres);
    }

    public static function relatedCandidates(string $bucket, array $genres, string $excludeId = '', string $excludeSlug = '', int $limit = 80): array
    {
        $params = [$bucket];
        $where = ['bucket = ?'];
        if ($excludeId !== '') {
            $where[] = 'id <> ?';
            $params[] = $excludeId;
        }
        if ($excludeSlug !== '') {
            $where[] = 'slug <> ?';
            $params[] = $excludeSlug;
        }

        $genres = array_values(array_filter(array_map('strval', $genres)));
        if ($genres) {
            $genreClauses = [];
            foreach ($genres as $genre) {
                $genreClauses[] = 'genres_text LIKE ?';
                $params[] = '%|' . $genre . '|%';
            }
            $where[] = '(' . implode(' OR ', $genreClauses) . ')';
        }

        $stmt = self::execute(
            'SELECT payload, created_at, updated_at FROM records WHERE ' . implode(' AND ', $where) . ' ORDER BY RAND() LIMIT ?',
            array_merge($params, [$limit])
        );
        return self::recordsFromResult($stmt->get_result());
    }

    public static function tmdbCacheGet(string $key): ?array
    {
        $stmt = self::execute('SELECT response FROM tmdb_cache WHERE cache_key = ? AND expires_at > ? LIMIT 1', [$key, time()]);
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row || (string)$row['response'] === '') return null;
        $decoded = json_decode((string)$row['response'], true);
        return is_array($decoded) ? $decoded : null;
    }

    public static function tmdbCachePut(string $key, string $body, int $ttl): void
    {
        self::execute('DELETE FROM tmdb_cache WHERE expires_at <= ?', [time()]);
        self::execute(
            'INSERT INTO tmdb_cache (cache_key, response, expires_at, created_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE response = VALUES(response), expires_at = VALUES(expires_at), created_at = VALUES(created_at)',
            [$key, $body, time() + $ttl, gmdate(DATE_ATOM)]
        );
    }

    public static function connection(): mysqli
    {
        if (self::$connection instanceof mysqli) {
            self::ensureSchema();
            return self::$connection;
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $host = (string)Config::get('DB_HOST', '127.0.0.1');
        $port = (int)Config::get('DB_PORT', 3306);
        $database = (string)Config::get('DB_DATABASE', 'stream_hive');
        $username = (string)Config::get('DB_USERNAME', 'root');
        $password = (string)Config::get('DB_PASSWORD', '');

        $db = new mysqli($host, $username, $password, '', $port);
        $db->set_charset('utf8mb4');
        $db->query('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $database) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $db->select_db($database);
        self::$connection = $db;
        self::ensureSchema();
        return self::$connection;
    }

    public static function stats(): array
    {
        $stmt = self::execute('SELECT bucket, COUNT(*) AS total FROM records GROUP BY bucket');
        $buckets = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $buckets[(string)$row['bucket']] = (int)$row['total'];
        }
        return [
            'path' => self::databaseName(),
            'size_bytes' => 0,
            'buckets' => $buckets,
        ];
    }

    public static function databaseName(): string
    {
        return (string)Config::get('DB_DATABASE', 'stream_hive');
    }

    public static function waitUntilRecordReadable(string $bucket, string $slug, int $timeoutMs = 2500): bool
    {
        $deadline = microtime(true) + ($timeoutMs / 1000);
        do {
            try {
                $stmt = self::execute('SELECT COUNT(*) AS total FROM records WHERE bucket = ? AND slug = ?', [$bucket, $slug]);
                if ((int)($stmt->get_result()->fetch_assoc()['total'] ?? 0) > 0) return true;
            } catch (\Throwable) {
            }
            usleep(50000);
        } while (microtime(true) < $deadline);
        return false;
    }

    private static function upsertRecord(string $bucket, array $record): void
    {
        $now = gmdate(DATE_ATOM);
        $id = (string)($record['id'] ?? $record['tmdb_id'] ?? '');
        if ($id === '') return;
        $title = (string)($record['title'] ?? '');
        $name = (string)($record['name'] ?? '');
        $mediaType = (string)($record['media_type'] ?? self::mediaTypeForBucket($bucket));
        $slug = (string)($record['slug'] ?? self::slugify($title !== '' ? $title : ($name !== '' ? $name : $id)));
        $record['id'] = is_numeric($id) ? (int)$id : $id;
        $record['tmdb_id'] ??= is_numeric($id) ? (int)$id : $id;
        $record['slug'] = $slug;
        $record['media_type'] = $mediaType;
        if ($title === '' && $name !== '') $record['title'] = $name;

        $ageRating = self::ukAgeRating($record['age_rating'] ?? '');
        if ($ageRating !== '') $record['age_rating'] = $ageRating;

        self::execute(
            'INSERT INTO records (bucket, id, slug, title, name, media_type, imdb_id, import_status, release_date, release_year, age_rating, genres_text, search_text, vote_average, popularity, created_at, updated_at, payload)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                slug = VALUES(slug),
                title = VALUES(title),
                name = VALUES(name),
                media_type = VALUES(media_type),
                imdb_id = VALUES(imdb_id),
                import_status = VALUES(import_status),
                release_date = VALUES(release_date),
                release_year = VALUES(release_year),
                age_rating = VALUES(age_rating),
                genres_text = VALUES(genres_text),
                search_text = VALUES(search_text),
                vote_average = VALUES(vote_average),
                popularity = VALUES(popularity),
                updated_at = VALUES(updated_at),
                payload = VALUES(payload)',
            [
                $bucket,
                $id,
                $slug,
                $title,
                $name,
                $mediaType,
                (string)($record['imdb_id'] ?? ''),
                (string)($record['import_status'] ?? 'full'),
                (string)($record['release_date'] ?? $record['first_air_date'] ?? ''),
                self::extractYear($record),
                $ageRating,
                self::genresText($record),
                self::searchText($record),
                isset($record['vote_average']) ? (float)$record['vote_average'] : null,
                isset($record['popularity']) ? (float)$record['popularity'] : null,
                $record['created_at'] ?? $now,
                $now,
                json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    private static function ensureSchema(): void
    {
        if (self::$schemaReady || !(self::$connection instanceof mysqli)) return;

        self::$connection->query(
            'CREATE TABLE IF NOT EXISTS records (
                bucket VARCHAR(32) NOT NULL,
                id VARCHAR(64) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                title VARCHAR(512) DEFAULT NULL,
                name VARCHAR(512) DEFAULT NULL,
                media_type VARCHAR(32) DEFAULT NULL,
                imdb_id VARCHAR(64) DEFAULT NULL,
                import_status VARCHAR(32) DEFAULT NULL,
                release_date VARCHAR(16) DEFAULT NULL,
                release_year VARCHAR(8) DEFAULT NULL,
                age_rating VARCHAR(32) DEFAULT NULL,
                genres_text TEXT DEFAULT NULL,
                search_text TEXT DEFAULT NULL,
                vote_average DECIMAL(5,3) DEFAULT NULL,
                popularity DECIMAL(12,3) DEFAULT NULL,
                created_at VARCHAR(40) NOT NULL,
                updated_at VARCHAR(40) NOT NULL,
                payload LONGTEXT NOT NULL,
                PRIMARY KEY (bucket, id),
                UNIQUE KEY idx_records_bucket_slug (bucket, slug),
                KEY idx_records_bucket_updated (bucket, updated_at),
                KEY idx_records_bucket_title (bucket, title),
                KEY idx_records_bucket_name (bucket, name),
                KEY idx_records_bucket_release (bucket, release_date),
                KEY idx_records_bucket_imdb (bucket, imdb_id),
                KEY idx_records_bucket_status (bucket, import_status),
                KEY idx_records_bucket_year (bucket, release_year)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::$connection->query(
            'CREATE TABLE IF NOT EXISTS tmdb_cache (
                cache_key VARCHAR(64) NOT NULL PRIMARY KEY,
                response LONGTEXT NOT NULL,
                expires_at INT UNSIGNED NOT NULL,
                created_at VARCHAR(40) NOT NULL,
                KEY idx_tmdb_cache_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::$schemaReady = true;
    }

    private static function execute(string $sql, array $params = []): mysqli_stmt
    {
        $stmt = self::connection()->prepare($sql);
        if ($params) {
            $types = '';
            $values = [];
            foreach ($params as $value) {
                if (is_int($value)) $types .= 'i';
                elseif (is_float($value)) $types .= 'd';
                else $types .= 's';
                $values[] = $value === null ? null : $value;
            }
            $refs = [];
            foreach ($values as $i => &$value) $refs[$i] = &$value;
            $stmt->bind_param($types, ...$refs);
        }
        $stmt->execute();
        return $stmt;
    }

    private static function recordsFromResult(mysqli_result $result): array
    {
        $records = [];
        foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
            $record = self::decode((string)$row['payload']);
            if (!$record) continue;
            $record['created_at'] ??= $row['created_at'] ?? null;
            $record['updated_at'] ??= $row['updated_at'] ?? null;
            $records[] = $record;
        }
        return $records;
    }

    private static function recordFromResult(mysqli_result $result): ?array
    {
        $row = $result->fetch_assoc();
        if (!$row) return null;
        $record = self::decode((string)$row['payload']);
        if (!$record) return null;
        $record['created_at'] ??= $row['created_at'] ?? null;
        $record['updated_at'] ??= $row['updated_at'] ?? null;
        return $record;
    }

    private static function decode(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function orderByForSort(string $sort, bool $containsPeople = false): string
    {
        $nameExpr = $containsPeople ? "COALESCE(NULLIF(name, ''), NULLIF(title, ''), '')" : "COALESCE(NULLIF(title, ''), NULLIF(name, ''), '')";
        return match ($sort) {
            'title_desc' => $nameExpr . ' DESC, id DESC',
            'date_asc' => "COALESCE(release_date, '') ASC, {$nameExpr} ASC",
            'date_desc' => "COALESCE(release_date, '') DESC, {$nameExpr} ASC",
            'rating_asc' => 'COALESCE(vote_average, 0) ASC, ' . $nameExpr . ' ASC',
            'rating_desc' => 'COALESCE(vote_average, 0) DESC, ' . $nameExpr . ' ASC',
            'updated_desc' => 'updated_at DESC, id DESC',
            'popular_desc' => 'COALESCE(popularity, 0) DESC, COALESCE(vote_average, 0) DESC, id DESC',
            default => $nameExpr . ' ASC, id ASC',
        };
    }

    private static function ukAgeRating(int|string|null $rating): string
    {
        $value = strtoupper(trim((string)$rating));
        if ($value === '' || in_array($value, ['NR','N/R','NOT RATED','UNRATED','TBC','TBD','N/A','NA'], true)) return '';
        $value = str_replace(['_', '.'], ['-', ''], $value);
        $value = preg_replace('/\s+/', '', $value) ?: $value;
        return match ($value) {
            'U', 'G', 'TV-G', 'TV-Y' => 'U',
            'PG', 'TV-PG', 'TV-Y7', 'TV-Y7-FV' => 'PG',
            '12' => '12',
            '12A', 'PG-13' => '12A',
            '15', 'R', 'TV-14', 'M' => '15',
            '18', 'NC-17', 'X', 'TV-MA' => '18',
            default => in_array($value, ['U','PG','12','12A','15','18'], true) ? $value : '',
        };
    }

    private static function ukAgeRatingAliases(string $ukRating): array
    {
        return match ($ukRating) {
            'U' => ['U', 'G', 'TV-G', 'TV-Y'],
            'PG' => ['PG', 'TV-PG', 'TV-Y7', 'TV-Y7-FV'],
            '12' => ['12'],
            '12A' => ['12A', 'PG-13'],
            '15' => ['15', 'R', 'TV-14', 'M'],
            '18' => ['18', 'NC-17', 'X', 'TV-MA'],
            default => [],
        };
    }

    private static function extractYear(array $record): string
    {
        $date = (string)($record['release_date'] ?? $record['first_air_date'] ?? '');
        return preg_match('/^\d{4}/', $date, $m) ? $m[0] : '';
    }

    private static function genresText(array $record): string
    {
        $genres = [];
        foreach (($record['genres'] ?? []) as $genre) {
            if (is_array($genre)) $genre = (string)($genre['name'] ?? '');
            $genre = trim((string)$genre);
            if ($genre !== '') $genres[strtolower($genre)] = $genre;
        }
        return $genres ? '|' . implode('|', array_values($genres)) . '|' : '';
    }

    private static function searchText(array $record): string
    {
        $parts = [
            (string)($record['title'] ?? ''),
            (string)($record['name'] ?? ''),
            (string)($record['overview'] ?? ''),
            (string)($record['biography'] ?? ''),
            (string)($record['known_for_department'] ?? ''),
        ];
        foreach (($record['genres'] ?? []) as $genre) $parts[] = is_array($genre) ? (string)($genre['name'] ?? '') : (string)$genre;
        foreach (($record['known_for'] ?? []) as $credit) if (is_array($credit)) $parts[] = (string)($credit['title'] ?? $credit['name'] ?? '');
        foreach (($record['credits'] ?? []) as $credit) if (is_array($credit)) $parts[] = (string)($credit['title'] ?? $credit['name'] ?? '');
        return strtolower(trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($parts))) ?: ''));
    }

    private static function recordHasUsablePoster(array $record): bool
    {
        $poster = trim((string)($record['poster_path'] ?? ''));
        return $poster !== ''
            && !str_contains($poster, 'placeholder.jpg')
            && !str_contains($poster, 'placeholder.svg');
    }

    private static function isHeroCarouselMovie(array $movie, string $cutoff, string $today): bool
    {
        if (!self::recordHasUsablePoster($movie)) return false;
        if ((string)($movie['original_language'] ?? '') !== 'en') return false;
        if ((float)($movie['vote_average'] ?? $movie['rating'] ?? 0) < 8.0) return false;
        $releaseDate = (string)($movie['release_date'] ?? $movie['first_air_date'] ?? '');
        return $releaseDate !== '' && $releaseDate >= $cutoff && $releaseDate <= $today;
    }

    private static function mediaTypeForBucket(string $bucket): string
    {
        return match ($bucket) {
            'tv' => 'tv',
            'people' => 'person',
            default => 'movie',
        };
    }

    private static function slugify(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = trim((string)$text, '-');
        $text = strtolower($text);
        return $text ?: 'item';
    }
}
