<?php

declare(strict_types=1);

namespace App\Services;

final class JsonShardStore
{
    public function __construct(private readonly string $dir, private readonly int $maxPerFile = 100)
    {
        if (!is_dir($this->dir)) mkdir($this->dir, 0775, true);
    }

    public function all(): array
    {
        $records = [];
        foreach ($this->files() as $file) {
            $records = array_merge($records, $this->readFile($file));
        }
        return $records;
    }

    public function find(int|string $id): ?array
    {
        foreach ($this->files() as $file) {
            foreach ($this->readFile($file) as $record) {
                if ((string)($record['id'] ?? '') === (string)$id) return $record;
            }
        }
        return null;
    }

    public function findBy(string $field, mixed $value): ?array
    {
        foreach ($this->all() as $record) {
            if ((string)($record[$field] ?? '') === (string)$value) return $record;
        }
        return null;
    }

    public function upsert(array $record): void
    {
        if (!isset($record['id'])) throw new \InvalidArgumentException('Record requires id');
        foreach ($this->files() as $file) {
            $rows = $this->readFile($file);
            foreach ($rows as $i => $row) {
                if ((string)($row['id'] ?? '') === (string)$record['id']) {
                    $rows[$i] = array_replace_recursive($row, $record, ['updated_at' => gmdate(DATE_ATOM)]);
                    $this->writeFile($file, $rows);
                    return;
                }
            }
        }
        $file = $this->firstWritableFile();
        $rows = $this->readFile($file);
        $record['created_at'] = $record['created_at'] ?? gmdate(DATE_ATOM);
        $record['updated_at'] = gmdate(DATE_ATOM);
        $rows[] = $record;
        $this->writeFile($file, $rows);
    }

    public function delete(int|string $id): bool
    {
        foreach ($this->files() as $file) {
            $rows = $this->readFile($file);
            $new = array_values(array_filter($rows, fn($r) => (string)($r['id'] ?? '') !== (string)$id));
            if (count($new) !== count($rows)) {
                $this->writeFile($file, $new);
                return true;
            }
        }
        return false;
    }

    private function files(): array
    {
        $files = glob($this->dir . '/*.json') ?: [];
        sort($files, SORT_NATURAL);
        return $files;
    }

    private function firstWritableFile(): string
    {
        $files = $this->files();
        foreach ($files as $file) {
            if (count($this->readFile($file)) < $this->maxPerFile) return $file;
        }
        $next = count($files) + 1;
        return $this->dir . '/' . str_pad((string)$next, 4, '0', STR_PAD_LEFT) . '.json';
    }

    private function readFile(string $file): array
    {
        if (!is_file($file)) return [];
        $json = json_decode((string)file_get_contents($file), true);
        return is_array($json) ? $json : [];
    }

    private function writeFile(string $file, array $rows): void
    {
        if (count($rows) > $this->maxPerFile) throw new \RuntimeException('Shard exceeds max entries');
        file_put_contents($file, json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}
