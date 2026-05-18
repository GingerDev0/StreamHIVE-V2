<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$service = new App\Services\ImportService();
foreach (array_slice($argv, 1) as $input) {
    try { $r = $service->importInput($input); echo "Imported " . ($r['title'] ?? $r['name']) . PHP_EOL; }
    catch (Throwable $e) { echo "Failed {$input}: {$e->getMessage()}" . PHP_EOL; }
}
