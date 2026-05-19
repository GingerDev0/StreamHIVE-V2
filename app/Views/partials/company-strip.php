<?php
/** @var array $companies */
/** @var string $title */
/** @var string $icon */
$companies = array_values(array_filter($companies ?? [], static fn($company): bool => is_array($company) && trim((string)($company['name'] ?? '')) !== ''));
$title = $title ?? 'Studios';
$icon = $icon ?? 'fa-building';
$variant = $variant ?? '';
$isHero = $variant === 'hero';
?>
<?php if ($companies): ?>
<section class="v2-company-strip <?= $isHero ? 'v2-company-strip--hero mb-3' : 'my-4' ?>" aria-label="<?= e($title) ?>">
  <?php if (!$isHero): ?>
  <div class="v2-section-head compact mb-3">
    <div>
      <span class="v2-section-eyebrow"><i class="fa-solid <?= e($icon) ?>"></i> Details</span>
      <h2><?= e($title) ?></h2>
    </div>
  </div>
  <?php endif; ?>
  <div class="v2-company-grid">
    <?php foreach ($companies as $company): ?>
      <article class="v2-company-card">
        <div class="v2-company-logo-wrap">
          <?php if (!empty($company['logo_path'])): ?>
            <img src="<?= e(tmdb_img($company['logo_path'], 'w185')) ?>" alt="<?= e($company['name']) ?> logo" loading="lazy">
          <?php else: ?>
            <span><?= e(strtoupper(substr((string)$company['name'], 0, 1))) ?></span>
          <?php endif; ?>
        </div>
        <div class="v2-company-name"><?= e($company['name']) ?></div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php unset($companies, $title, $icon, $variant, $isHero); ?>
