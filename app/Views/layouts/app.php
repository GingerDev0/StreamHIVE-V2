<?php require_once app_path('app/Helpers/helpers.php'); ?>
<?php
$siteName = 'Movie DB';
$pageTitleRaw = trim((string)($title ?? 'Movie DB V2'));
$pageTitle = str_contains($pageTitleRaw, $siteName) ? $pageTitleRaw : $pageTitleRaw . ' | ' . $siteName;
$pageDescription = meta_excerpt((string)($metaDescription ?? 'Explore trending movies and TV shows with cast, episodes, ratings, genres, and instant playback in a bold cinematic layout.'), 165);
$canonicalUrl = (string)($canonicalUrl ?? current_url());
$ogTitle = (string)($ogTitle ?? $pageTitle);
$ogDescription = meta_excerpt((string)($ogDescription ?? $pageDescription), 200);
$ogType = (string)($ogType ?? 'website');
$ogImage = (string)($ogImage ?? absolute_url(asset('img/placeholder.svg')));
$robots = (string)($robots ?? 'index, follow');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($pageDescription) ?>">
  <meta name="robots" content="<?= e($robots) ?>">
  <meta name="theme-color" content="#080d18">
  <link rel="canonical" href="<?= e($canonicalUrl) ?>">

  <meta property="og:site_name" content="<?= e($siteName) ?>">
  <meta property="og:type" content="<?= e($ogType) ?>">
  <meta property="og:title" content="<?= e($ogTitle) ?>">
  <meta property="og:description" content="<?= e($ogDescription) ?>">
  <meta property="og:url" content="<?= e($canonicalUrl) ?>">
  <meta property="og:image" content="<?= e($ogImage) ?>">
  <meta property="og:image:alt" content="<?= e($ogTitle) ?>">

  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($ogTitle) ?>">
  <meta name="twitter:description" content="<?= e($ogDescription) ?>">
  <meta name="twitter:image" content="<?= e($ogImage) ?>">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body class="v2-body">
<div class="v2-orb v2-orb-one"></div>
<div class="v2-orb v2-orb-two"></div>
<nav class="navbar navbar-expand-lg navbar-dark v2-nav sticky-top">
  <div class="container-fluid px-3 px-lg-4">
    <a class="navbar-brand v2-brand" href="/">
      <span class="v2-brand-mark"><i class="fa-solid fa-bolt"></i></span>
      <span><strong>Movie DB</strong><small>V2</small></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav mx-lg-auto mb-2 mb-lg-0 v2-nav-pills">
        <li class="nav-item"><a class="nav-link" href="/"><i class="fa-solid fa-house"></i> Home</a></li>
        <li class="nav-item"><a class="nav-link" href="/movies"><i class="fa-solid fa-film"></i> Movies</a></li>
        <li class="nav-item"><a class="nav-link" href="/tv"><i class="fa-solid fa-tv"></i> TV Shows</a></li>
        <li class="nav-item"><a class="nav-link" href="/s"><i class="fa-solid fa-sliders"></i> Discover</a></li>
        <li class="nav-item"><a class="nav-link" href="/coming-this-year"><i class="fa-solid fa-calendar-days"></i> Coming This Year</a></li>
        <li class="nav-item"><a class="nav-link" href="/profile"><i class="fa-solid fa-user-astronaut"></i> My Profile</a></li>
      </ul>
      <form class="v2-nav-search" action="/s" method="get" role="search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input name="q" placeholder="Search the universe..." autocomplete="off">
        <button type="submit">Go</button>
      </form>
      <a class="v2-admin-link" href="/admin?token=change-this-token" title="Admin"><i class="fa-solid fa-gear"></i></a>
    </div>
  </div>
</nav>
<main class="container-fluid px-3 px-lg-4 py-4 v2-main"> <?= $content ?> </main>
<footer class="container-fluid px-3 px-lg-4 py-4 small v2-footer">
  <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
    <span>Created by <a href="https://github.com/GingerDev0" target="_blank" rel="noopener noreferrer">GingerDev</a></span>
    <span>TMDB data powers imports. This product is not endorsed or certified by TMDB.</span>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body></html>
