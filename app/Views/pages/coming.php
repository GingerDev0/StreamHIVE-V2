<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php
  $comingBackdropPool = array_values(array_filter(array_merge($movies ?? [], $tvShows ?? []), static function (array $item): bool {
      return !empty($item['backdrop_path']) || !empty($item['poster_path']);
  }));
  $comingHeroItem = $comingBackdropPool ? $comingBackdropPool[array_rand($comingBackdropPool)] : null;
  $comingHeroBackdrop = $comingHeroItem ? tmdb_img($comingHeroItem['backdrop_path'] ?? ($comingHeroItem['poster_path'] ?? null), !empty($comingHeroItem['backdrop_path']) ? 'w1280' : 'w780') : '';
?>
<section class="coming-hero glass rounded-4 overflow-hidden mb-4<?= $comingHeroBackdrop !== '' ? ' has-backdrop' : '' ?>"<?= $comingHeroBackdrop !== '' ? ' style="--coming-hero-bg:url(' . e($comingHeroBackdrop) . ')"' : '' ?>>
  <?php if ($comingHeroBackdrop !== ''): ?><div class="coming-hero-backdrop" aria-hidden="true"></div><?php endif; ?>
  <div class="coming-hero-glow"></div>
  <div class="coming-hero-inner">
    <span class="v2-kicker"><i class="fa-solid fa-calendar-days"></i> Coming this year</span>
    <h1>Coming in <?= e((string)$year) ?></h1>
    <p>Upcoming movies and TV shows scheduled for this year. These titles are listed for discovery only and unlock when their release date arrives.</p>
    <div class="coming-hero-stats">
      <span><strong><?= e((string)count($movies)) ?></strong> Movies</span>
      <span><strong><?= e((string)count($tvShows)) ?></strong> TV Shows</span>
    </div>
  </div>
</section>

<section class="coming-tabs-shell glass rounded-4 p-3 p-lg-4">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <span class="v2-section-eyebrow"><i class="fa-solid fa-clock"></i> Release calendar</span>
      <h2 class="mb-0 text-white fw-black">Upcoming releases</h2>
    </div>
    <div class="coming-tabs" role="tablist" aria-label="Coming this year tabs">
      <button class="coming-tab active" type="button" data-coming-tab="movie"><i class="fa-solid fa-film"></i> Movies <span><?= e((string)count($movies)) ?></span></button>
      <button class="coming-tab" type="button" data-coming-tab="tv"><i class="fa-solid fa-tv"></i> TV Shows <span><?= e((string)count($tvShows)) ?></span></button>
    </div>
  </div>

  <?php foreach ([['movie', 'Movies', $movies], ['tv', 'TV Shows', $tvShows]] as [$tabType, $tabLabel, $items]): ?>
    <div class="coming-panel <?= $tabType === 'movie' ? 'active' : '' ?>" data-coming-panel="<?= e($tabType) ?>">
      <?php if ($items): ?>
        <div class="coming-grid" data-coming-grid="<?= e($tabType) ?>" data-per-page="18">
          <?php foreach ($items as $item):
            $title = (string)($item['title'] ?? 'Untitled');
            $date = media_release_date($item);
            $prettyDate = format_date($date);
            $poster = tmdb_img($item['poster_path'] ?? null, 'w500');
            $rating = round((float)($item['vote_average'] ?? 0), 1);
            $genres = array_values(array_filter(array_map('strval', $item['genres'] ?? [])));
          ?>
            <article class="coming-card" data-coming-item>
              <div class="coming-poster" aria-label="<?= e($title) ?> is not released yet">
                <img src="<?= e($poster) ?>" alt="<?= e($title) ?> poster" loading="lazy">
                <span class="coming-poster-gradient"></span>
                <span class="coming-soon-pill"><i class="fa-solid fa-lock"></i> Locked until release</span>
                <?php if ($rating > 0): ?><span class="coming-rating"><i class="fa-solid fa-star"></i> <?= e((string)$rating) ?></span><?php endif; ?>
              </div>
              <div class="coming-copy">
                <span class="coming-title"><?= e($title) ?></span>
                <?php if ($prettyDate !== ''): ?><span class="coming-date"><i class="fa-solid fa-calendar-days"></i> <?= e($prettyDate) ?></span><?php endif; ?>
                <?php if ($genres): ?><span class="coming-genres"><?= genre_links($genres, $tabType, 2, 'genre-link genre-link-home') ?></span><?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
        <div class="coming-footer mt-4" data-coming-pagination="<?= e($tabType) ?>"></div>
      <?php else: ?>
        <div class="coming-empty text-center py-5">
          <i class="fa-solid <?= $tabType === 'movie' ? 'fa-film' : 'fa-tv' ?> mb-3"></i>
          <h3>No upcoming <?= e(strtolower($tabLabel)) ?> found yet</h3>
          <p class="text-white-50 mb-0">TMDB results will appear here after imports/prefetching have data for this year.</p>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
