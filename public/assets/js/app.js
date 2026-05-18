document.documentElement.classList.add('js-ready');

(() => {
  const KEYS = {
    bookmarks: 'movieDB.bookmarks.v1',
    continue: 'movieDB.continueWatching.v1',
    recent: 'movieDB.recent.v1'
  };
  const LIMITS = { bookmarks: 80, continue: 24, recent: 36 };

  const read = (key) => {
    try { return JSON.parse(localStorage.getItem(KEYS[key]) || '[]'); }
    catch { return []; }
  };
  const write = (key, items) => localStorage.setItem(KEYS[key], JSON.stringify(items.slice(0, LIMITS[key] || 50)));
  const parseMedia = (el) => {
    try { return JSON.parse(el?.dataset?.media || '{}'); }
    catch { return {}; }
  };
  const mediaKey = (item) => {
    const type = item.type || 'media';
    const id = type === 'episode'
      ? (item.url || item.slug || item.title || 'unknown')
      : (item.tmdb_id || item.slug || item.url || item.title || 'unknown');
    return `${type}:${id}`;
  };
  const normalize = (item) => ({
    type: item.type || 'media',
    tmdb_id: item.tmdb_id || null,
    slug: item.slug || '',
    title: item.title || 'Untitled',
    url: item.url || '#',
    poster: item.poster || '/assets/img/placeholder.svg',
    backdrop: item.backdrop || item.poster || '/assets/img/placeholder.svg',
    year: item.year || '',
    rating: item.rating || '',
    meta: item.meta || '',
    savedAt: item.savedAt || Date.now(),
    lastWatchedAt: item.lastWatchedAt || Date.now(),
    viewedAt: item.viewedAt || Date.now()
  });
  const upsert = (key, item, timeField) => {
    if (!item || !item.title) return;
    const media = normalize({ ...item, [timeField]: Date.now() });
    const k = mediaKey(media);
    const items = read(key).filter((entry) => mediaKey(entry) !== k);
    items.unshift(media);
    write(key, items);
  };
  const removeBookmark = (item) => {
    const k = mediaKey(item);
    write('bookmarks', read('bookmarks').filter((entry) => mediaKey(entry) !== k));
  };
  const hasBookmark = (item) => read('bookmarks').some((entry) => mediaKey(entry) === mediaKey(item));

  const setBookmarkButtonState = (button, saved) => {
    button.classList.toggle('is-saved', saved);
    button.setAttribute('aria-pressed', saved ? 'true' : 'false');
    const icon = button.querySelector('i');
    if (icon) {
      icon.classList.toggle('fa-solid', saved);
      icon.classList.toggle('fa-regular', !saved);
    }
    if (button.classList.contains('detail-bookmark')) {
      const text = button.childNodes[button.childNodes.length - 1];
      if (text && text.nodeType === Node.TEXT_NODE) text.nodeValue = saved ? ' Saved to profile' : (button.textContent.includes('episode') ? ' Save episode' : ' Save to profile');
    }
  };

  const syncBookmarkButtons = () => {
    document.querySelectorAll('.js-bookmark-btn[data-media]').forEach((button) => {
      setBookmarkButtonState(button, hasBookmark(parseMedia(button)));
    });
  };

  document.addEventListener('click', (event) => {
    const button = event.target.closest('.js-bookmark-btn[data-media]');
    if (!button) return;
    event.preventDefault();
    event.stopPropagation();
    const item = parseMedia(button);
    if (hasBookmark(item)) removeBookmark(item);
    else upsert('bookmarks', item, 'savedAt');
    syncBookmarkButtons();
    renderProfile();
  });

  document.addEventListener('click', (event) => {
    const link = event.target.closest('.js-media-link[data-media]');
    if (!link || event.target.closest('.js-bookmark-btn')) return;
    upsert('recent', parseMedia(link), 'viewedAt');
  });

  const pageMedia = document.querySelector('.js-page-media[data-media]');
  if (pageMedia) upsert('recent', parseMedia(pageMedia), 'viewedAt');

  const continueMedia = document.querySelector('.js-continue-media[data-media]');
  if (continueMedia) upsert('continue', parseMedia(continueMedia), 'lastWatchedAt');

  const escapeHtml = (value) => String(value || '').replace(/[&<>'"]/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[ch]));
  const typeLabel = (type) => type === 'tv' ? 'TV' : (type === 'episode' ? 'Episode' : 'Movie');
  const renderCard = (item, section) => {
    const saved = hasBookmark(item);
    const meta = item.meta || [typeLabel(item.type), item.year].filter(Boolean).join(' · ');
    return `<article class="profile-card">
      <a href="${escapeHtml(item.url)}" class="profile-card-link js-media-link" data-media='${escapeHtml(JSON.stringify(item))}'>
        <img src="${escapeHtml(item.poster)}" alt="${escapeHtml(item.title)} poster" loading="lazy">
        <span class="profile-card-gradient"></span>
        <span class="profile-type">${escapeHtml(typeLabel(item.type))}</span>
        ${item.rating ? `<span class="profile-rating"><i class="fa-solid fa-star"></i> ${escapeHtml(item.rating)}</span>` : ''}
        <span class="profile-card-copy"><strong>${escapeHtml(item.title)}</strong><small>${escapeHtml(meta)}</small></span>
      </a>
      <button class="profile-remove ${section === 'bookmarks' ? 'js-bookmark-btn' : 'js-profile-remove'} ${saved ? 'is-saved' : ''}" type="button" data-section="${escapeHtml(section)}" data-media='${escapeHtml(JSON.stringify(item))}' aria-label="${section === 'bookmarks' ? 'Remove bookmark' : 'Remove item'}"><i class="${section === 'bookmarks' || saved ? 'fa-solid fa-bookmark' : 'fa-solid fa-xmark'}"></i></button>
    </article>`;
  };

  function renderProfile() {
    const root = document.querySelector('[data-profile-section]');
    if (!root) return;
    ['bookmarks', 'continue', 'recent'].forEach((section) => {
      const items = read(section);
      const grid = document.querySelector(`[data-profile-grid="${section}"]`);
      const empty = document.querySelector(`[data-profile-empty="${section}"]`);
      const count = document.querySelector(`[data-profile-count="${section === 'continue' ? 'continue' : section}"]`);
      if (count) count.textContent = String(items.length);
      if (grid) grid.innerHTML = items.slice(0, section === 'bookmarks' ? 30 : 18).map((item) => renderCard(item, section)).join('');
      if (empty) empty.classList.toggle('d-none', items.length > 0);
    });
  }

  document.addEventListener('click', (event) => {
    const clear = event.target.closest('.js-profile-clear[data-clear]');
    if (!clear) return;
    write(clear.dataset.clear, []);
    syncBookmarkButtons();
    renderProfile();
  });

  document.addEventListener('click', (event) => {
    const remove = event.target.closest('.js-profile-remove[data-section][data-media]');
    if (!remove) return;
    const section = remove.dataset.section;
    const item = parseMedia(remove);
    const key = mediaKey(item);
    write(section, read(section).filter((entry) => mediaKey(entry) !== key));
    renderProfile();
  });

  window.addEventListener('storage', () => { syncBookmarkButtons(); renderProfile(); });
  syncBookmarkButtons();
  renderProfile();
})();

/* Actor profile tabs + jQuery pagination */
(function ($) {
  if (!$ || !$('.actor-credits-shell').length) return;

  const renderActorPage = function (type, page) {
    const $grid = $('[data-actor-grid="' + type + '"]');
    const $footer = $('[data-actor-pagination="' + type + '"]');
    if (!$grid.length || !$footer.length) return;

    const perPage = parseInt($grid.data('per-page'), 10) || 12;
    const $items = $grid.find('[data-actor-credit-item]');
    const total = $items.length;
    const pages = Math.max(1, Math.ceil(total / perPage));
    const current = Math.min(Math.max(1, page || 1), pages);
    const start = (current - 1) * perPage;
    const end = start + perPage;

    $items.hide().slice(start, end).fadeIn(140);

    if (pages <= 1) {
      $footer.empty();
      return;
    }

    const visibleFrom = start + 1;
    const visibleTo = Math.min(total, end);
    let html = '<div class="actor-pager-bar">';
    const label = type === 'tv' ? 'TV Shows' : (type === 'production' ? 'In Production' : 'Movies');
    html += '<div class="pager-showing"><span>Showing</span><strong>' + visibleFrom + (visibleTo !== visibleFrom ? '&ndash;' + visibleTo : '') + '</strong><span>of</span><strong>' + total + '</strong><span>' + label + '</span></div>';
    html += '<div class="actor-pager-actions">';
    if (current > 1) html += '<button type="button" class="actor-page-btn" data-actor-page="' + type + '" data-page="' + (current - 1) + '" aria-label="Previous page"><i class="fa-solid fa-angle-left"></i></button>';
    html += '<span class="actor-page-current">Page <strong>' + current + '</strong> of ' + pages + '</span>';
    if (current < pages) html += '<button type="button" class="actor-page-btn" data-actor-page="' + type + '" data-page="' + (current + 1) + '" aria-label="Next page"><i class="fa-solid fa-angle-right"></i></button>';
    html += '</div></div>';
    $footer.html(html);
  };

  const activateActorTab = function (type) {
    $('.actor-tab').removeClass('active').attr('aria-selected', 'false');
    $('.actor-tab[data-actor-tab="' + type + '"]').addClass('active').attr('aria-selected', 'true');
    $('.actor-credit-panel').removeClass('active').hide();
    $('[data-actor-panel="' + type + '"]').addClass('active').fadeIn(140);
    renderActorPage(type, 1);
  };

  $(document).on('click', '.actor-tab[data-actor-tab]', function () {
    activateActorTab($(this).data('actor-tab'));
  });

  $(document).on('click', '.actor-page-btn[data-actor-page][data-page]', function () {
    const type = $(this).data('actor-page');
    const page = parseInt($(this).data('page'), 10) || 1;
    renderActorPage(type, page);
    const $panel = $('[data-actor-panel="' + type + '"]');
    if ($panel.length) $('html, body').animate({ scrollTop: Math.max(0, $panel.offset().top - 120) }, 220);
  });

  $('.actor-credit-panel').hide();
  activateActorTab($('.actor-tab.active').data('actor-tab') || 'movie');
})(window.jQuery);

/* Coming this year tabs + jQuery pagination */
(function ($) {
  if (!$ || !$('.coming-tabs-shell').length) return;

  const renderComingPage = function (type, page) {
    const $grid = $('[data-coming-grid="' + type + '"]');
    const $footer = $('[data-coming-pagination="' + type + '"]');
    if (!$grid.length || !$footer.length) return;

    const perPage = parseInt($grid.data('per-page'), 10) || 18;
    const $items = $grid.find('[data-coming-item]');
    const total = $items.length;
    const pages = Math.max(1, Math.ceil(total / perPage));
    const current = Math.min(Math.max(1, page || 1), pages);
    const start = (current - 1) * perPage;
    const end = start + perPage;

    $items.hide().slice(start, end).fadeIn(140);

    if (pages <= 1) {
      $footer.empty();
      return;
    }

    const visibleFrom = start + 1;
    const visibleTo = Math.min(total, end);
    const label = type === 'tv' ? 'TV Shows' : 'Movies';
    let html = '<div class="actor-pager-bar coming-pager-bar">';
    html += '<div class="pager-showing"><span>Showing</span><strong>' + visibleFrom + (visibleTo !== visibleFrom ? '&ndash;' + visibleTo : '') + '</strong><span>of</span><strong>' + total + '</strong><span>' + label + '</span></div>';
    html += '<div class="actor-pager-actions coming-pager-actions">';
    if (current > 1) html += '<button type="button" class="actor-page-btn coming-page-btn" data-coming-page="' + type + '" data-page="' + (current - 1) + '" aria-label="Previous page"><i class="fa-solid fa-angle-left"></i></button>';
    html += '<span class="actor-page-current">Page <strong>' + current + '</strong> of ' + pages + '</span>';
    if (current < pages) html += '<button type="button" class="actor-page-btn coming-page-btn" data-coming-page="' + type + '" data-page="' + (current + 1) + '" aria-label="Next page"><i class="fa-solid fa-angle-right"></i></button>';
    html += '</div></div>';
    $footer.html(html);
  };

  const activateComingTab = function (type) {
    $('.coming-tab').removeClass('active').attr('aria-selected', 'false');
    $('.coming-tab[data-coming-tab="' + type + '"]').addClass('active').attr('aria-selected', 'true');
    $('.coming-panel').removeClass('active').hide();
    $('[data-coming-panel="' + type + '"]').addClass('active').fadeIn(140);
    renderComingPage(type, 1);
  };

  $(document).on('click', '.coming-tab[data-coming-tab]', function () {
    activateComingTab($(this).data('coming-tab'));
  });

  $(document).on('click', '.coming-page-btn[data-coming-page][data-page]', function () {
    const type = $(this).data('coming-page');
    const page = parseInt($(this).data('page'), 10) || 1;
    renderComingPage(type, page);
    const $panel = $('[data-coming-panel="' + type + '"]');
    if ($panel.length) $('html, body').animate({ scrollTop: Math.max(0, $panel.offset().top - 120) }, 220);
  });

  $('.coming-panel').hide();
  activateComingTab($('.coming-tab.active').data('coming-tab') || 'movie');
})(window.jQuery);
