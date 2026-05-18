<?php use App\Core\View; require_once app_path('app/Helpers/helpers.php');
$featured = $moviesTrending[0] ?? $moviesRecent[0] ?? $tvTrending[0] ?? null;
$featuredType = (($featured['media_type'] ?? '') === 'tv') ? 'tv' : 'movie';
$featuredTitle = $featured ? ($featured['title'] ?? $featured['name'] ?? 'Featured') : 'Featured';
$featuredLink = $featured ? (($featuredType === 'tv') ? url('tv/' . slugify($featuredTitle)) : url('movies/' . slugify($featuredTitle))) : url('movies');
?>
<section class="v2-hero-shell mb-4">
  <?php if ($featured): ?><div class="v2-hero-bg" style="background-image:url('<?= e(tmdb_img($featured['backdrop_path'] ?? ($featured['poster_path'] ?? null), 'w1280')) ?>')"></div><?php endif; ?>
  <div class="v2-hero-grid">
    <div class="v2-hero-copy">
      <span class="v2-kicker"><i class="fa-solid fa-wand-magic-sparkles"></i> Your next watch starts here</span>
      <h1>Streamlined discovery for movies, shows, actors and episodes.</h1>
      <p>Explore trending movies and TV shows with cast, episodes, ratings, genres, and instant playback in a bold cinematic layout.</p>
      <div class="v2-hero-actions">
        <a class="btn btn-warning btn-lg" href="/movies"><i class="fa-solid fa-film me-2"></i>Browse Movies</a>
        <a class="btn btn-outline-light btn-lg" href="/tv"><i class="fa-solid fa-tv me-2"></i>Browse TV</a>
        <a class="btn btn-outline-light btn-lg" href="/s"><i class="fa-solid fa-filter me-2"></i>Advanced Search</a>
      </div>
    </div>
    <?php if ($featured): ?>
    <a class="v2-feature-card" href="<?= e($featuredLink) ?>">
      <img src="<?= e(tmdb_img($featured['poster_path'] ?? null, 'w500')) ?>" alt="<?= e($featuredTitle) ?> poster">
      <div class="v2-feature-info">
        <span class="v2-kicker">Tonight's spotlight</span>
        <h2><?= e($featuredTitle) ?></h2>
        <div class="v2-chip-row"><span><i class="fa-solid fa-star"></i> <?= e((string)round((float)($featured['vote_average'] ?? 0), 1)) ?></span><span><?= e($featuredType === 'tv' ? 'TV Show' : 'Movie') ?></span></div>
      </div>
      <span class="v2-play-float"><i class="fa-solid fa-play"></i></span>
    </a>
    <?php endif; ?>
  </div>
</section>

<?php foreach ([['Recent Movies',$moviesRecent,'movie','fa-fire'],['Trending Movies',$moviesTrending,'movie','fa-arrow-trend-up'],['Recent TV Shows',$tvRecent,'tv','fa-satellite-dish'],['Trending TV Shows',$tvTrending,'tv','fa-bolt']] as [$heading,$items,$type,$icon]): ?>
<section class="v2-section mb-5">
  <div class="v2-section-head">
    <div><span class="v2-section-eyebrow"><i class="fa-solid <?= e($icon) ?>"></i> <?= e($type === 'tv' ? 'Series' : 'Cinema') ?></span><h2><?= e($heading) ?></h2></div>
    <a href="<?= e($type === 'tv' ? url('tv') : url('movies')) ?>">View all <i class="fa-solid fa-arrow-right"></i></a>
  </div>
  <div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-2 g-lg-3 home-card-grid v2-home-grid"><?php foreach (array_slice($items, 0, 12) as $item) { $variant = 'home'; echo View::partial('partials/media-card', compact('item','type','variant')); } ?></div>
</section>
<?php endforeach; ?>
