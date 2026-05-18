<?php require_once app_path('app/Helpers/helpers.php'); ?>
<aside class="glass rounded-4 p-3 text-white h-100">
  <h2 class="h4 mb-3"><i class="fa-solid fa-clapperboard me-2 text-warning"></i>More like this</h2>
  <?php if (empty($related)): ?>
    <p class="small text-white-50 mb-0">Import more <?= e(($type ?? 'movie') === 'tv' ? 'TV shows' : 'movies') ?> to build recommendations.</p>
  <?php else: ?>
    <div class="d-flex flex-column gap-3">
      <?php foreach ($related as $rel): ?>
        <?php
          $relType = $type ?? ($rel['media_type'] ?? 'movie');
          $relLink = $relType === 'tv' ? url('tv/' . ($rel['slug'] ?? slugify($rel['title'] ?? 'item'))) : url('movies/' . ($rel['slug'] ?? slugify($rel['title'] ?? 'item')));
        ?>
        <a class="d-flex gap-3 text-decoration-none align-items-start" href="<?= e($relLink) ?>">
          <img class="rounded-3 flex-shrink-0" src="<?= e(tmdb_img($rel['poster_path'] ?? null, 'w185')) ?>" alt="<?= e($rel['title'] ?? 'Poster') ?>" style="width:72px;aspect-ratio:2/3;object-fit:cover;">
          <span class="d-block">
            <span class="d-block text-white fw-semibold lh-sm"><?= e($rel['title'] ?? 'Untitled') ?></span>
            <span class="d-block small text-white-50 mt-1"><?= e(format_year((string)($rel['release_date'] ?? ''))) ?><?= !empty($rel['age_rating']) ? ' · ' . e((string)$rel['age_rating']) : '' ?></span>
            <span class="d-block small text-warning mt-1"><i class="fa-solid fa-star"></i> <?= e((string)round((float)($rel['vote_average'] ?? 0), 1)) ?></span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</aside>
