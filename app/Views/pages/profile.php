<section class="profile-hero glass rounded-5 p-4 p-lg-5 mb-4">
  <div class="row g-4 align-items-center">
    <div class="col-lg-8">
      <span class="v2-kicker"><i class="fa-solid fa-user-astronaut"></i> My Profile</span>
      <h1 class="display-5 fw-black mb-3">Your saved movies, shows and watch history.</h1>
      <p class="v2-lead mb-0">Bookmarks and recently viewed history are stored in this browser with localStorage, so the page updates instantly without an account.</p>
    </div>
    <div class="col-lg-4">
      <div class="profile-stats-grid">
        <div class="profile-stat"><strong data-profile-count="bookmarks">0</strong><span>Saved</span></div>
        <div class="profile-stat"><strong data-profile-count="recent">0</strong><span>Recent</span></div>
      </div>
    </div>
  </div>
</section>


<section class="profile-section mb-5" data-profile-section="bookmarks">
  <div class="v2-section-head compact">
    <div><span class="v2-section-eyebrow"><i class="fa-solid fa-bookmark"></i> Saved for later</span><h2>Bookmarks</h2></div>
    <button class="btn btn-sm btn-outline-light js-profile-clear" type="button" data-clear="bookmarks">Clear</button>
  </div>
  <div class="profile-grid" data-profile-grid="bookmarks"></div>
  <div class="profile-empty glass rounded-4 p-4" data-profile-empty="bookmarks">Tap the bookmark icon on any poster card to save it here.</div>
</section>

<section class="profile-section" data-profile-section="recent">
  <div class="v2-section-head compact">
    <div><span class="v2-section-eyebrow"><i class="fa-solid fa-clock-rotate-left"></i> Recently opened</span><h2>Recently Viewed</h2></div>
    <button class="btn btn-sm btn-outline-light js-profile-clear" type="button" data-clear="recent">Clear</button>
  </div>
  <div class="profile-grid" data-profile-grid="recent"></div>
  <div class="profile-empty glass rounded-4 p-4" data-profile-empty="recent">Movies, shows and episodes you open will show up here.</div>
</section>
