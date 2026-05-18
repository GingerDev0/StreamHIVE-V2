<?php require_once app_path('app/Helpers/helpers.php');
$type = $type ?? (($item['media_type'] ?? '') === 'person' ? 'person' : (($item['media_type'] ?? '') === 'tv' ? 'tv' : 'movie'));
$variant = $variant ?? 'poster';
$title = $item['title'] ?? $item['name'] ?? 'Untitled';
$slug = $item['slug'] ?? slugify($title);
$link = $type === 'person' ? url('actors/' . $slug) : ($type === 'tv' ? url('tv/' . $slug) : url('movies/' . $slug));
$rating = round((float)($item['vote_average'] ?? 0), 1);
$genres = array_values(array_filter(array_map('strval', $item['genres'] ?? [])));
$genreText = genre_links($genres, $type, 2, 'genre-link genre-link-home');
$ageRating = trim((string)($item['age_rating'] ?? '')) ?: 'NR';
$overview = trim((string)($item['overview'] ?? $item['biography'] ?? '')) ?: 'No overview available yet.';
?>
<?php if ($type === 'person'): ?>
<?php
$knownFor = [];
foreach (($item['known_for'] ?? []) as $credit) $knownFor[] = $credit['title'] ?? '';
foreach (($item['credits'] ?? []) as $credit) $knownFor[] = $credit['title'] ?? '';
$knownFor = array_values(array_unique(array_filter(array_map('strval', $knownFor))));
$profile = tmdb_img($item['profile_path'] ?? null, 'w500');
?>
<div class="col-6 col-md-3 col-xl-2">
  <a class="card media-card h-100 text-decoration-none person-result-card" href="<?= e($link) ?>">
    <img src="<?= e($profile) ?>" class="card-img-top" alt="<?= e($title) ?> profile">
    <div class="card-body">
      <h3 class="h6 card-title text-white mb-1"><?= e($title) ?></h3>
      <div class="small text-white-50"><?= e((string)($item['known_for_department'] ?? 'Actor')) ?></div>
      <?php if ($knownFor): ?><div class="small text-warning mt-1"><?= e(implode(' · ', array_slice($knownFor, 0, 2))) ?></div><?php endif; ?>
    </div>
  </a>
</div>
<?php elseif ($variant === 'home'): ?>
<?php
$year = substr((string)($item['release_date'] ?? $item['first_air_date'] ?? ''), 0, 4);
$homePoster = tmdb_img($item['poster_path'] ?? null, 'w500');
?>
<div class="col">
  <a class="home-poster-card js-media-link" href="<?= e($link) ?>" aria-label="Open <?= e($title) ?>" data-media="<?= media_storage_payload($item, $type, $link) ?>">
    <img src="<?= e($homePoster) ?>" class="home-poster-card-img" alt="<?= e($title) ?> poster">
    <button class="home-bookmark js-bookmark-btn" type="button" aria-label="Save <?= e($title) ?>" data-media="<?= media_storage_payload($item, $type, $link) ?>"><i class="fa-regular fa-bookmark"></i></button>
    <span class="home-poster-play"><i class="fa-solid fa-play"></i></span>
    <span class="home-card-sheen" aria-hidden="true"></span>
    <span class="home-poster-gradient" aria-hidden="true"></span>
    <span class="home-poster-content">
      <span class="home-rating-badge"><?= e((string)$rating) ?></span>
      <span class="home-poster-title"><?= e($title) ?></span>
      <?php if ($year !== ''): ?><span class="home-poster-year"><?= e($year) ?></span><?php endif; ?>
    </span>
  </a>
</div>
<?php else: ?>
<div class="col-6 col-md-3 col-xl-2">
  <a class="card media-card h-100 text-decoration-none js-media-link" href="<?= e($link) ?>" data-media="<?= media_storage_payload($item, $type, $link) ?>">
    <img src="<?= e(tmdb_img($item['poster_path'] ?? null)) ?>" class="card-img-top" alt="<?= e($title) ?>">
    <div class="card-body"><h3 class="h6 card-title text-white mb-1"><?= e($title) ?></h3>
    <div class="small text-warning"><i class="fa-solid fa-star"></i> <?= e((string)$rating) ?></div></div>
  </a>
</div>
<?php endif; ?>
