<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php $token = (string)($_GET['token'] ?? ''); ?>
<section class="admin-hero admin-panel mb-4">
  <div class="row g-4 align-items-center">
    <div class="col-lg-8">
      <span class="admin-kicker"><i class="fa-solid fa-shield-halved"></i> Control room</span>
      <h1 class="admin-title">Admin Dashboard</h1>
      <p class="admin-lead mb-0">Import, review, search, and clean up your local movie and TV library from one polished workspace.</p>
    </div>
    <div class="col-lg-4">
      <div class="admin-actions">
        <a class="btn btn-warning w-100" href="/admin/import?token=<?= e($token) ?>"><i class="fa-solid fa-cloud-arrow-down me-2"></i>Import media</a>
        <a class="btn btn-outline-light w-100" href="/admin/manage/movies?token=<?= e($token) ?>"><i class="fa-solid fa-film me-2"></i>Manage movies</a>
        <a class="btn btn-outline-light w-100" href="/admin/manage/tv?token=<?= e($token) ?>"><i class="fa-solid fa-tv me-2"></i>Manage TV shows</a>
      </div>
    </div>
  </div>
</section>

<div class="row g-3 mb-4">
  <?php foreach ([['Movies',$movieCount,'fa-film','/admin/manage/movies'], ['TV Shows',$tvCount,'fa-tv','/admin/manage/tv'], ['Actors',$peopleCount,'fa-user-group','/admin/manage/people'], ['Needs full import',$prefetchedCount,'fa-wand-magic-sparkles','/admin/manage/movies?status=prefetched']] as $stat): ?>
    <div class="col-sm-6 col-xl-3">
      <a class="admin-stat-card" href="<?= e($stat[3] . (str_contains($stat[3], '?') ? '&' : '?') . 'token=' . rawurlencode($token)) ?>">
        <span class="admin-stat-icon"><i class="fa-solid <?= e($stat[2]) ?>"></i></span>
        <span class="admin-stat-value"><?= e((string)$stat[1]) ?></span>
        <span class="admin-stat-label"><?= e($stat[0]) ?></span>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-4">
  <div class="col-xl-8">
    <section class="admin-panel h-100">
      <div class="admin-section-head">
        <div>
          <span class="admin-kicker"><i class="fa-solid fa-clock-rotate-left"></i> Latest changes</span>
          <h2>Recently Updated</h2>
        </div>
        <a class="btn btn-sm btn-outline-light" href="/admin/import?token=<?= e($token) ?>">Add new</a>
      </div>
      <div class="admin-recent-list">
        <?php foreach ($recentItems as $item): ?>
          <?php
            $type = (string)($item['admin_type'] ?? 'movies');
            $titleText = (string)($item['title'] ?? $item['name'] ?? 'Untitled');
            $publicUrl = $type === 'tv' ? url('tv/' . ($item['slug'] ?? slugify($titleText))) : url('movies/' . ($item['slug'] ?? slugify($titleText)));
          ?>
          <div class="admin-recent-row">
            <img src="<?= e(tmdb_img($item['poster_path'] ?? null, 'w185')) ?>" alt="<?= e($titleText) ?> poster">
            <div class="min-w-0">
              <a class="admin-recent-title" href="<?= e($publicUrl) ?>"><?= e($titleText) ?></a>
              <div class="admin-muted small"><?= e(strtoupper($type === 'tv' ? 'TV' : 'Movie')) ?><?= !empty($item['updated_at']) ? ' · Updated ' . e(format_date(substr((string)$item['updated_at'], 0, 10))) : '' ?></div>
            </div>
            <span class="admin-chip ms-auto"><?= e((string)round((float)($item['vote_average'] ?? 0), 1)) ?> ★</span>
          </div>
        <?php endforeach; ?>
        <?php if (empty($recentItems)): ?><div class="admin-empty">No media has been imported yet.</div><?php endif; ?>
      </div>
    </section>
  </div>
  <div class="col-xl-4">
    <section class="admin-panel h-100">
      <div class="admin-section-head"><div><span class="admin-kicker"><i class="fa-solid fa-database"></i> SQLite</span><h2>Storage Health</h2></div></div>
      <div class="d-flex flex-column gap-3">
        <?php foreach ($storageStats as $name => $stat): ?>
          <div class="admin-storage-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <strong><?= e(ucfirst($name)) ?></strong>
              <span class="admin-muted small"><?= e((string)$stat['rows']) ?> records</span>
            </div>
            <div class="admin-progress"><span style="width: <?= e((string)$stat['percent']) ?>%"></span></div>
            <div class="admin-muted small mt-2"><?= e(format_bytes((int)($stat['size_bytes'] ?? 0))) ?> SQLite database</div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="admin-note mt-3"><i class="fa-solid fa-circle-info me-2"></i>SQLite is now the primary local database. Existing JSON shards are imported automatically the first time the app starts.</div>
    </section>
  </div>
</div>
