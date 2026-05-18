<?php require_once app_path('app/Helpers/helpers.php'); use App\Core\View; ?>
<section class="glass rounded-4 p-4 mb-4 text-white">
  <h1 class="h2 mb-3">Search</h1>
  <form class="row g-2" method="get" action="<?= e(url('s')) ?>">
    <div class="col-md-4"><input name="q" value="<?= e($query) ?>" class="form-control" placeholder="Search movies, TV shows, genres"></div>
    <div class="col-md-2"><select name="type" class="form-select"><option value="all" <?= $type==='all'?'selected':'' ?>>Movies + TV</option><option value="movie" <?= $type==='movie'?'selected':'' ?>>Movies</option><option value="tv" <?= $type==='tv'?'selected':'' ?>>TV Shows</option><option value="person" <?= $type==='person'?'selected':'' ?>>Actors</option></select></div>
    <div class="col-md-2"><select name="genre" class="form-select"><option value="">All genres</option><?php foreach ($genres as $g): ?><option value="<?= e($g) ?>" <?= $genre===$g?'selected':'' ?>><?= e($g) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select name="rating" class="form-select"><option value="">Any rating</option><?php foreach ($ratings as $r): ?><option value="<?= e($r) ?>" <?= $rating===$r?'selected':'' ?>><?= e($r) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><input name="year" value="<?= e($year) ?>" class="form-control" placeholder="Year"></div>
    <div class="col-md-10"><select name="sort" class="form-select">
      <?php foreach (['title_asc'=>'Title A-Z','title_desc'=>'Title Z-A','date_desc'=>'Newest release','date_asc'=>'Oldest release','rating_desc'=>'Top rated','rating_asc'=>'Lowest rated','updated_desc'=>'Recently updated'] as $value=>$label): ?>
        <option value="<?= e($value) ?>" <?= $sort===$value?'selected':'' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select></div>
    <div class="col-md-2 d-grid"><button class="btn btn-warning"><i class="fa-solid fa-magnifying-glass me-1"></i>Search</button></div>
  </form>
</section>

<div class="d-flex justify-content-between align-items-center mb-3 text-white"><h2 class="h5 mb-0">Results</h2><span class="text-white-50"><?= e((string)$total) ?> found</span></div>
<div class="row g-3">
<?php foreach ($items as $item): echo View::partial('partials/media-card', ['item'=>$item, 'type'=>$item['media_type'] ?? 'movie']); endforeach; ?>
<?php if (!$items): ?><div class="col-12"><div class="glass rounded-4 p-4 text-white">No results found yet. Try a different title, genre, year, or rating filter. Select Actors to search people.</div></div><?php endif; ?>
</div>

<?= View::partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => $perPage ?? 24, 'itemLabel' => $type === 'movie' ? 'Movies' : ($type === 'tv' ? 'TV Shows' : ($type === 'person' ? 'Actors' : 'Results'))]) ?>
