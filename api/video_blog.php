<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/settings.php';

$d    = db();
$site = getAppearanceSettings($d);

// Ensure columns exist silently
try { $d->exec("ALTER TABLE tracks ADD COLUMN lyrics TEXT"); } catch (Exception $e) {}
try { $d->exec("ALTER TABLE tracks ADD COLUMN is_new TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}

// Fetch only published tracks
$tracks = $d->query("
    SELECT * FROM tracks
    WHERE is_published = true
    ORDER BY sort_order ASC, id DESC
")->fetchAll();

// Active filter from query string
$filter  = $_GET['filter'] ?? 'all';
$search  = trim($_GET['q'] ?? '');

// Increment play count via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['play_id'])) {
    $pid = (int)$_POST['play_id'];
    $d->exec("UPDATE tracks SET plays = plays + 1 WHERE id=$pid");
    echo json_encode(['ok' => true]);
    exit;
}

// Separate featured
$featured = array_filter($tracks, fn($t) => $t['is_featured']);
$featured = array_values($featured);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($site['logo_text']) ?> — Video</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════════
   ROOT & RESET
══════════════════════════════════════════ */
:root {
  --bg:       #09090e;
  --surface:  #111118;
  --card:     #15151d;
  --card-h:   #1c1c27;
  --border:   rgba(255,255,255,0.07);
  --border-h: rgba(255,255,255,0.13);
  --green:    #ff0000;
  --green-d:  #ff0000;
  --accent:   #a855f7;
  --red:      #ef4444;
  --yellow:   #f59e0b;
  --text:     #e8e8f0;
  --muted:    #5a5a78;
  --dim:      #333348;
  --font-head:'Syne', sans-serif;
  --font:     'DM Sans', sans-serif;
  --r:        12px;
  --transition: .2s cubic-bezier(.4,0,.2,1);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
  font-family: var(--font);
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  overflow-x: hidden;
}
a { color: inherit; text-decoration: none; }
img { display: block; }

/* ══════════════════════════════════════════
   SCROLLBAR
══════════════════════════════════════════ */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: var(--bg); }
::-webkit-scrollbar-thumb { background: var(--dim); border-radius: 3px; }

/* ══════════════════════════════════════════
   TOPNAV
══════════════════════════════════════════ */
.nav {
  position: sticky; top: 0; z-index: 200;
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 32px; height: 60px;
  background: rgba(9,9,14,0.85);
  backdrop-filter: blur(14px);
  border-bottom: 1px solid var(--border);
}
.nav-logo {
  display: flex; align-items: center; gap: 10px;
  font-family: var(--font-head); font-size: 1.1rem; font-weight: 800;
}
.nav-logo-dot {
  width: 28px; height: 28px; border-radius: 50%;
  background: var(--green);
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; color: #000;
  flex-shrink: 0;
}
.nav-links { display: flex; align-items: center; gap: 6px; }
.nav-link {
  padding: 7px 14px; border-radius: 8px;
  font-size: .82rem; font-weight: 500; color: var(--muted);
  transition: color var(--transition), background var(--transition);
}
.nav-link:hover, .nav-link.active { color: var(--text); background: var(--card); }
.nav-link.active { color: var(--green); }
.nav-search-wrap {
  position: relative; display: flex; align-items: center;
}
.nav-search {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 24px;
  color: var(--text);
  font-family: var(--font); font-size: .85rem;
  padding: 8px 16px 8px 38px;
  outline: none; width: 200px;
  transition: border-color .2s, width .2s;
}
.nav-search:focus { border-color: var(--green); width: 260px; }
.nav-search-icon {
  position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
  color: var(--muted); pointer-events: none;
}

/* ══════════════════════════════════════════
   HERO
══════════════════════════════════════════ */
.hero {
  padding: 64px 32px 48px;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.hero::before {
  content: '';
  position: absolute; inset: 0;
background: radial-gradient(ellipse 60% 40% at 50% 0%, rgba(255,0,0,.12) 0%, transparent 70%);  pointer-events: none;
}
.hero-label {
  display: inline-block;
background: rgba(255,0,0,.12);
border: 1px solid rgba(255,0,0,.25);
  color: var(--green);
  font-size: .72rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
  padding: 5px 14px; border-radius: 20px; margin-bottom: 20px;
}
.hero-title {
  font-family: var(--font-head); font-size: clamp(2.2rem, 5vw, 3.6rem);
  font-weight: 800; line-height: 1.08;
  background: linear-gradient(135deg, #e8e8f0 30%, #8888aa);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
  margin-bottom: 16px;
}
.hero-sub {
  font-size: .95rem; color: var(--muted); max-width: 480px; margin: 0 auto 36px;
  line-height: 1.6;
}
.hero-filters {
  display: flex; align-items: center; justify-content: center;
  gap: 8px; flex-wrap: wrap;
}
.filter-btn {
  padding: 8px 18px; border-radius: 20px;
  border: 1px solid var(--border);
  background: var(--card); color: var(--muted);
  font-family: var(--font); font-size: .82rem; font-weight: 600;
  cursor: pointer; transition: all var(--transition);
}
.filter-btn:hover { border-color: var(--border-h); color: var(--text); }
.filter-btn.active {
  background: var(--green); border-color: var(--green);
  color: #000;
}

/* ══════════════════════════════════════════
   FEATURED STRIP
══════════════════════════════════════════ */
.featured-section {
  padding: 0 32px 48px;
}
.section-label {
  font-size: .7rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;
  color: var(--muted); margin-bottom: 16px;
  display: flex; align-items: center; gap: 8px;
}
.section-label::after {
  content: ''; flex: 1; height: 1px; background: var(--border);
}
.featured-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}

/* ══════════════════════════════════════════
   TRACK CARD (grid/featured)
══════════════════════════════════════════ */
.track-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--r);
  overflow: hidden;
  cursor: pointer;
  transition: border-color var(--transition), transform var(--transition), box-shadow var(--transition);
  position: relative;
}
.track-card:hover {
  border-color: var(--border-h);
  transform: translateY(-3px);
  box-shadow: 0 12px 40px rgba(0,0,0,.5);
}
.track-card-thumb {
  width: 100%; aspect-ratio: 16/9;
  background: var(--surface);
  position: relative; overflow: hidden;
}
.track-card-thumb img {
  width: 100%; height: 100%; object-fit: cover;
  transition: transform .4s ease;
}
.track-card:hover .track-card-thumb img { transform: scale(1.05); }
.track-card-thumb-placeholder {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  font-size: 2.5rem;
}
.track-card-play-btn {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  background: rgba(0,0,0,0);
  transition: background var(--transition);
}
.track-card:hover .track-card-play-btn { background: rgba(0,0,0,.4); }
.track-card-play-icon {
  width: 52px; height: 52px; border-radius: 50%;
background: rgba(255,0,0,.9);
  display: flex; align-items: center; justify-content: center;
  opacity: 0; transform: scale(.8);
  transition: opacity var(--transition), transform var(--transition);
}
.track-card:hover .track-card-play-icon { opacity: 1; transform: scale(1); }
.track-card-play-icon svg { margin-left: 3px; }

.badge-new {
  position: absolute; top: 10px; left: 10px;
  background: var(--green); color: #000;
  font-size: .62rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
  padding: 3px 8px; border-radius: 4px;
}
.badge-feat {
  position: absolute; top: 10px; right: 10px;
  background: rgba(168,85,247,.85); color: #fff;
  font-size: .62rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
  padding: 3px 8px; border-radius: 4px;
}

.track-card-body { padding: 14px 16px; }
.track-card-meta {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 6px;
}
.track-card-type {
  font-size: .65rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
  color: var(--accent);
}
.track-card-dur { font-size: .72rem; color: var(--muted); }
.track-card-title {
  font-family: var(--font-head); font-size: 1rem; font-weight: 700;
  margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.track-card-artist { font-size: .78rem; color: var(--muted); }
.track-card-footer {
  display: flex; align-items: center; justify-content: space-between;
  margin-top: 10px; padding-top: 10px;
  border-top: 1px solid var(--border);
}
.track-card-plays { font-size: .72rem; color: var(--dim); display: flex; align-items: center; gap: 4px; }
.track-card-src {
  font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;
  padding: 3px 8px; border-radius: 4px;
}
.src-yt  { background: rgba(239,68,68,.12); color: #f87171; }
.src-mp3 { background: rgba(255,0,0,.12); color: #f87171; }
.mtag-new  { background: rgba(255,0,0,.15); color: var(--green); }

/* ══════════════════════════════════════════
   ALL TRACKS SECTION (list view)
══════════════════════════════════════════ */
.tracks-section { padding: 0 32px 80px; }
.tracks-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
}

/* ══════════════════════════════════════════
   VIDEO MODAL
══════════════════════════════════════════ */
.modal-overlay {
  position: fixed; inset: 0; z-index: 500;
  background: rgba(0,0,0,.88);
  backdrop-filter: blur(6px);
  display: none; align-items: center; justify-content: center;
  padding: 20px;
}
.modal-overlay.open { display: flex; }
.modal-box {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 16px;
  width: 100%; max-width: 860px;
  max-height: 95vh; overflow-y: auto;
  scrollbar-width: thin; scrollbar-color: var(--dim) transparent;
  animation: modalIn .25s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes modalIn {
  from { opacity:0; transform: scale(.94) translateY(20px); }
  to   { opacity:1; transform: scale(1)  translateY(0); }
}
.modal-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  padding: 20px 24px 0; gap: 16px;
}
.modal-close {
  background: var(--surface); border: 1px solid var(--border); border-radius: 8px;
  color: var(--muted); font-size: 18px; cursor: pointer; line-height: 1;
  width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; transition: color var(--transition), border-color var(--transition);
}
.modal-close:hover { color: var(--text); border-color: var(--border-h); }

/* Video embed area */
.modal-video-wrap {
  margin: 16px 24px 0;
  border-radius: 10px;
  background: #000;
  /* NO overflow:hidden — it was clipping the iframe to 0 height */
  /* Use padding-top trick for reliable 16/9 on all browsers */
  position: relative;
  width: 100%;
  padding-top: 56.25%; /* 9/16 = 56.25% — creates the 16:9 box */
  height: 0;
}
.modal-video-wrap iframe {
  position: absolute;
  top: 0; left: 0;
  width: 100%;
  height: 100%;
  border: none;
  display: block;
  border-radius: 10px;
}
.modal-video-wrap video {
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  border: none; display: block;
  border-radius: 10px;
}
/* Audio-only — override the padding-top trick since audio needs natural height */
.modal-video-wrap.audio-mode {
  padding-top: 0;
  height: auto;
}
/* Audio-only cover */
.modal-audio-cover {
  width: 100%;
  aspect-ratio: 16/9;
  display: flex; align-items: center; justify-content: center;
  font-size: 5rem;
  background: var(--surface);
  border-radius: 10px 10px 0 0;
}
.modal-audio-player {
  width: 100%;
  display: block;
  background: #000;
  border-radius: 0 0 10px 10px;
}

/* Info row */
.modal-info {
  padding: 20px 24px;
  display: flex; gap: 16px; flex-wrap: wrap;
  align-items: flex-start; justify-content: space-between;
}
.modal-info-left { flex: 1; min-width: 0; }
.modal-title {
  font-family: var(--font-head); font-size: 1.5rem; font-weight: 800;
  margin-bottom: 4px; line-height: 1.2;
}
.modal-artist { font-size: .9rem; color: var(--muted); margin-bottom: 12px; }
.modal-tags { display: flex; gap: 8px; flex-wrap: wrap; }
.modal-tag {
  padding: 4px 10px; border-radius: 5px; font-size: .7rem;
  font-weight: 700; text-transform: uppercase; letter-spacing: .07em;
}
.mtag-type { background: rgba(168,85,247,.15); color: #c084fc; }
.mtag-dur  { background: rgba(255,255,255,.06); color: var(--muted); }
.mtag-new  { background: rgba(29,185,84,.15); color: var(--green); }
.modal-info-right { text-align: right; flex-shrink: 0; }
.modal-plays { font-size: .78rem; color: var(--muted); }
.modal-plays strong { font-family: var(--font-head); font-size: 1.2rem; color: var(--text); }

/* Lyrics */
.modal-lyrics-wrap {
  margin: 0 24px 24px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
}
.modal-lyrics-toggle {
  width: 100%; padding: 14px 18px;
  background: none; border: none; cursor: pointer;
  color: var(--text); font-family: var(--font); font-size: .85rem; font-weight: 600;
  display: flex; align-items: center; justify-content: space-between;
  transition: background var(--transition);
}
.modal-lyrics-toggle:hover { background: var(--card); }
.modal-lyrics-body {
  display: none; padding: 0 18px 18px;
}
.modal-lyrics-body.open { display: block; }
.modal-lyrics-body pre {
  font-family: var(--font); font-size: .85rem; line-height: 1.9;
  color: var(--muted); white-space: pre-wrap; word-break: break-word;
}

/* ══════════════════════════════════════════
   EMPTY STATE
══════════════════════════════════════════ */
.empty {
  text-align: center; padding: 80px 20px;
  color: var(--muted); font-size: .9rem;
}
.empty-icon { font-size: 3rem; margin-bottom: 12px; }

/* ══════════════════════════════════════════
   FOOTER
══════════════════════════════════════════ */
.site-footer {
  border-top: 1px solid var(--border);
  padding: 24px 32px;
  display: flex; align-items: center; justify-content: space-between;
  font-size: .78rem; color: var(--muted); flex-wrap: wrap; gap: 12px;
}
.site-footer a { color: var(--muted); transition: color var(--transition); }
.site-footer a:hover { color: var(--text); }

/* ══════════════════════════════════════════
   NO-RESULTS banner
══════════════════════════════════════════ */
.no-results {
  grid-column: 1/-1;
  text-align: center; padding: 60px 20px;
  color: var(--muted); font-size: .88rem;
}

/* ══════════════════════════════════════════
   RESPONSIVE
══════════════════════════════════════════ */
@media (max-width: 640px) {
  .nav { padding: 0 16px; }
  .nav-links { display: none; }
  .hero { padding: 40px 16px 32px; }
  .featured-section, .tracks-section { padding-left: 16px; padding-right: 16px; }
  .modal-header, .modal-info { padding: 16px; }
  .modal-video-wrap, .modal-lyrics-wrap { margin-left: 16px; margin-right: 16px; }
  .modal-title { font-size: 1.2rem; }
}
</style>
</head>
<body>

<!-- ══ NAV ══ -->
<nav class="nav">
  <div class="nav-logo">
    <div class="nav-logo-dot">♪</div>
    <?= h($site['logo_text']) ?>
  </div>
<div class="nav-search-wrap">
    <svg class="nav-search-icon" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
    </svg>
    <input type="text" class="nav-search" id="liveSearch" placeholder="Search tracks…" oninput="liveFilter()">
  </div>
  <div class="nav-links">
    <a href="/" class="nav-link">Home</a>
    <a href="/music" class="nav-link ">Music</a>
    <a href="/videos" class="nav-link active">Videos</a>
    <a href="/blog" class="nav-link">Journal</a>
  </div>
 
</nav>

<!-- ══ HERO ══ -->
<section class="hero">
  <div class="hero-label"> Video Collection</div>
  <h1 class="hero-title"><?= h($site['logo_text']) ?></h1>
  <p class="hero-sub">Original tracks, covers &amp; more. Click any track to watch or listen.</p>
  <div class="hero-filters">
    <button class="filter-btn <?= $filter==='all'?'active':'' ?>" onclick="setFilter('all', event)">All</button>
    <button class="filter-btn <?= $filter==='original'?'active':'' ?>" onclick="setFilter('original', event)">Originals</button>
    <button class="filter-btn <?= $filter==='cover'?'active':'' ?>" onclick="setFilter('cover', event)">Covers</button>
    <button class="filter-btn <?= $filter==='remix'?'active':'' ?>" onclick="setFilter('remix', event)">Remixes</button>
    <button class="filter-btn <?= $filter==='live'?'active':'' ?>" onclick="setFilter('live', event)">Live</button>
    <button class="filter-btn <?= $filter==='acoustic'?'active':'' ?>" onclick="setFilter('acoustic', event)">Acoustic</button>
  </div>
</section>

<?php if ($featured): ?>
<!-- ══ FEATURED ══ -->
<section class="featured-section" id="featuredSection">
  <div class="section-label">Featured Tracks</div>
  <div class="featured-grid" id="featuredGrid">
    <?php foreach ($featured as $t): ?>
    <?php
      $hasCover = !empty($t['cover_image']);
      $hasYt    = !empty($t['youtube_id']);
      $hasFile  = !empty($t['file_path']);
      $thumbSrc = $hasCover
        ? '/' . h($t['cover_image'])
        : ($hasYt ? 'https://img.youtube.com/vi/' . h($t['youtube_id']) . '/hqdefault.jpg' : '');
      $src = $hasFile ? 'mp3' : ($hasYt ? 'yt' : 'none');
    ?>
    <?php
      $trackData = base64_encode(json_encode([
        'id'         => (int)$t['id'],
        'title'      => $t['title'],
        'artist'     => $t['artist'],
        'type'       => $t['type'] ?? 'original',
        'youtube_id' => $t['youtube_id'] ?? '',
        'file_path'  => $t['file_path'] ?? '',
        'cover_image'=> $t['cover_image'] ?? '',
        'cover_color'=> $t['cover_color'] ?? '',
        'duration'   => $t['duration'] ?? '',
        'lyrics'     => $t['lyrics'] ?? '',
        'plays'      => (int)$t['plays'],
        'is_new'     => (bool)($t['is_new'] ?? 0),
      ]));
    ?>
    <div class="track-card"
         data-type="<?= h($t['type'] ?? 'original') ?>"
         data-search="<?= h(strtolower($t['title'].' '.$t['artist'])) ?>"
         data-track="<?= $trackData ?>"
         onclick="openTrackFromEl(this)">
      <div class="track-card-thumb" style="<?= (!$hasCover && !$hasYt && !empty($t['cover_color'])) ? 'background:'.h($t['cover_color']).';' : '' ?>">
        <?php if ($thumbSrc): ?>
          <img src="<?= $thumbSrc ?>" alt="<?= h($t['title']) ?>">
        <?php else: ?>
          <div class="track-card-thumb-placeholder">🎵</div>
        <?php endif; ?>
        <div class="track-card-play-btn">
          <div class="track-card-play-icon">
            <svg width="20" height="20" fill="#000" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
          </div>
        </div>
        <?php if (!empty($t['is_new'])): ?><span class="badge-new">New</span><?php endif; ?>
        <span class="badge-feat">Featured</span>
      </div>
      <div class="track-card-body">
        <div class="track-card-meta">
          <span class="track-card-type"><?= h($t['type'] ?? 'original') ?></span>
          <?php if (!empty($t['duration'])): ?><span class="track-card-dur"><?= h($t['duration']) ?></span><?php endif; ?>
        </div>
        <div class="track-card-title"><?= h($t['title']) ?></div>
        <div class="track-card-artist"><?= h($t['artist']) ?></div>
        <div class="track-card-footer">
          <span class="track-card-plays">▶ <?= number_format($t['plays'] ?? 0) ?> plays</span>
          <span class="track-card-src src-<?= $src ?>">
            <?= $src === 'yt' ? 'YouTube' : ($src === 'mp3' ? 'Audio' : 'Preview') ?>
          </span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- ══ ALL TRACKS ══ -->
<section class="tracks-section">
  <div class="section-label" id="allTracksLabel">All Tracks</div>
  <?php if ($tracks): ?>
  <div class="tracks-grid" id="tracksGrid">
    <?php foreach ($tracks as $t): ?>
    <?php
      $hasCover = !empty($t['cover_image']);
      $hasYt    = !empty($t['youtube_id']);
      $hasFile  = !empty($t['file_path']);
      $thumbSrc = $hasCover
        ? '/' . h($t['cover_image'])
        : ($hasYt ? 'https://img.youtube.com/vi/' . h($t['youtube_id']) . '/hqdefault.jpg' : '');
      $src = $hasFile ? 'mp3' : ($hasYt ? 'yt' : 'none');
    ?>
    <?php
      $trackData = base64_encode(json_encode([
        'id'         => (int)$t['id'],
        'title'      => $t['title'],
        'artist'     => $t['artist'],
        'type'       => $t['type'] ?? 'original',
        'youtube_id' => $t['youtube_id'] ?? '',
        'file_path'  => $t['file_path'] ?? '',
        'cover_image'=> $t['cover_image'] ?? '',
        'cover_color'=> $t['cover_color'] ?? '',
        'duration'   => $t['duration'] ?? '',
        'lyrics'     => $t['lyrics'] ?? '',
        'plays'      => (int)$t['plays'],
        'is_new'     => (bool)($t['is_new'] ?? 0),
      ]));
    ?>
    <div class="track-card"
         data-type="<?= h($t['type'] ?? 'original') ?>"
         data-search="<?= h(strtolower($t['title'].' '.$t['artist'])) ?>"
         data-track="<?= $trackData ?>"
         onclick="openTrackFromEl(this)">
      <div class="track-card-thumb" style="<?= (!$hasCover && !$hasYt && !empty($t['cover_color'])) ? 'background:'.h($t['cover_color']).';' : '' ?>">
        <?php if ($thumbSrc): ?>
          <img src="<?= $thumbSrc ?>" alt="<?= h($t['title']) ?>">
        <?php else: ?>
          <div class="track-card-thumb-placeholder">🎵</div>
        <?php endif; ?>
        <div class="track-card-play-btn">
          <div class="track-card-play-icon">
            <svg width="20" height="20" fill="#000" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
          </div>
        </div>
        <?php if (!empty($t['is_new'])): ?><span class="badge-new">New</span><?php endif; ?>
      </div>
      <div class="track-card-body">
        <div class="track-card-meta">
          <span class="track-card-type"><?= h($t['type'] ?? 'original') ?></span>
          <?php if (!empty($t['duration'])): ?><span class="track-card-dur"><?= h($t['duration']) ?></span><?php endif; ?>
        </div>
        <div class="track-card-title"><?= h($t['title']) ?></div>
        <div class="track-card-artist"><?= h($t['artist']) ?></div>
        <div class="track-card-footer">
          <span class="track-card-plays">▶ <?= number_format($t['plays'] ?? 0) ?> plays</span>
          <span class="track-card-src src-<?= $src ?>">
            <?= $src === 'yt' ? 'YouTube' : ($src === 'mp3' ? 'Audio' : 'Preview') ?>
          </span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <div class="no-results" id="noResults" style="display:none">
      <div style="font-size:2rem;margin-bottom:8px">🔍</div>
      No tracks match your search.
    </div>
  </div>
  <?php else: ?>
  <div class="empty">
    <div class="empty-icon">🎵</div>
    No tracks published yet. Check back soon!
  </div>
  <?php endif; ?>
</section>

<!-- ══ FOOTER ══ -->
<footer class="site-footer">
  <span>© <?= date('Y') ?> <?= h($site['logo_text']) ?></span>
  <span>Built with ♪</span>
</footer>

<!-- ══ VIDEO / AUDIO MODAL ══ -->
<div class="modal-overlay" id="playerModal" onclick="handleOverlayClick(event)">
  <div class="modal-box" id="modalBox">
    <div class="modal-header">
      <div>
        <div id="mTitle" style="font-family:var(--font-head);font-size:1.4rem;font-weight:800;line-height:1.2;margin-bottom:4px"></div>
        <div id="mArtist" style="font-size:.85rem;color:var(--muted)"></div>
      </div>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>

    <!-- Media area -->
    <div class="modal-video-wrap" id="mMediaWrap">
      <!-- injected by JS -->
    </div>

    <!-- Info -->
    <div class="modal-info">
      <div class="modal-info-left">
        <div class="modal-tags" id="mTags"></div>
      </div>
      <div class="modal-info-right">
        <div class="modal-plays"><strong id="mPlays">0</strong><br>plays</div>
      </div>
    </div>

    <!-- Lyrics -->
    <div class="modal-lyrics-wrap" id="mLyricsWrap" style="display:none">
      <button class="modal-lyrics-toggle" onclick="toggleLyrics()">
        <span>📝 Lyrics</span>
        <span id="lyricsArrow">▼</span>
      </button>
      <div class="modal-lyrics-body" id="mLyricsBody">
        <pre id="mLyrics"></pre>
      </div>
    </div>
  </div>
</div>

<script>
// ══════════════════════════════════════════
// FILTER
// ══════════════════════════════════════════
let currentFilter = 'all';
let currentSearch = '';

function setFilter(f, e) {
  currentFilter = f;
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  if (e && e.currentTarget) e.currentTarget.classList.add('active');
  applyFilters();
}

function liveFilter() {
  currentSearch = document.getElementById('liveSearch').value.toLowerCase();
  applyFilters();
}

function applyFilters() {
  let anyVisible = false;
  document.querySelectorAll('#tracksGrid .track-card').forEach(card => {
    const typeMatch   = currentFilter === 'all' || card.dataset.type === currentFilter;
    const searchMatch = !currentSearch || (card.dataset.search || '').includes(currentSearch);
    const show        = typeMatch && searchMatch;
    card.style.display = show ? '' : 'none';
    if (show) anyVisible = true;
  });
  document.getElementById('noResults').style.display = anyVisible ? 'none' : 'block';

  // Featured section: hide cards that don't match filter
  document.querySelectorAll('#featuredGrid .track-card').forEach(card => {
    const typeMatch = currentFilter === 'all' || card.dataset.type === currentFilter;
    const searchMatch = !currentSearch || (card.dataset.search || '').includes(currentSearch);
    card.style.display = (typeMatch && searchMatch) ? '' : 'none';
  });

  // Hide featured section entirely if nothing visible
  const featuredSec = document.getElementById('featuredSection');
  if (featuredSec) {
    const anyFeat = [...document.querySelectorAll('#featuredGrid .track-card')]
      .some(c => c.style.display !== 'none');
    featuredSec.style.display = anyFeat ? '' : 'none';
  }
}

// ══════════════════════════════════════════
// MODAL PLAYER
// ══════════════════════════════════════════
let currentTrackId = null;

// Entry point: called by onclick on each card
function openTrackFromEl(el) {
  try {
    const t = JSON.parse(atob(el.dataset.track));
    openTrack(t);
  } catch(e) {
    console.error('Failed to parse track data:', e);
  }
}

function openTrack(t) {
  currentTrackId = t.id;

  // Track title / artist
  document.getElementById('mTitle').textContent  = t.title;
  document.getElementById('mArtist').textContent = t.artist;

  // Media area — clear previous content and mode classes
  const wrap = document.getElementById('mMediaWrap');
  wrap.innerHTML = '';
  wrap.classList.remove('audio-mode');

  if (t.youtube_id) {
    // ── YouTube embed ────────────────────────────────────────────────────
    // padding-top:56.25% trick is active (default), iframe fills it absolutely
    const iframe = document.createElement('iframe');
    iframe.src =
      'https://www.youtube.com/embed/' +
      encodeURIComponent(t.youtube_id) +
      '?autoplay=1&rel=0&playsinline=1&modestbranding=1&enablejsapi=0';
    iframe.setAttribute('allow',
      'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share'
    );
    iframe.setAttribute('allowfullscreen', '');
    iframe.setAttribute('frameborder', '0');
    wrap.appendChild(iframe);

  } else if (t.file_path) {
    // ── Audio file — natural height, no padding-top trick ────────────────
    wrap.classList.add('audio-mode');

    const coverDiv = document.createElement('div');
    coverDiv.className = 'modal-audio-cover';
    if (t.cover_image) {
      coverDiv.style.backgroundImage = 'url(/' + t.cover_image + ')';
      coverDiv.style.backgroundSize  = 'cover';
      coverDiv.style.backgroundPosition = 'center';
      coverDiv.innerHTML =
        '<div style="background:rgba(0,0,0,.55);width:100%;height:100%;' +
        'display:flex;align-items:center;justify-content:center;font-size:4rem;border-radius:10px 10px 0 0;">🎵</div>';
    } else {
      coverDiv.style.background = t.cover_color || 'var(--surface)';
      coverDiv.innerHTML = '🎵';
    }
    wrap.appendChild(coverDiv);

    const audio = document.createElement('audio');
    audio.controls = true;
    audio.autoplay  = true;
    audio.className = 'modal-audio-player';
    audio.src = '/' + t.file_path;
    wrap.appendChild(audio);

  } else {
    // ── No source — placeholder ──────────────────────────────────────────
    wrap.classList.add('audio-mode');
    const ph = document.createElement('div');
    ph.className = 'modal-audio-cover';
    ph.style.background = t.cover_color || 'var(--surface)';
    ph.innerHTML = '🎵';
    wrap.appendChild(ph);
  }

  // Tags
  const tagsEl = document.getElementById('mTags');
  tagsEl.innerHTML = '';
  const addTag = (txt, cls) => {
    const s = document.createElement('span');
    s.className = 'modal-tag ' + cls;
    s.textContent = txt;
    tagsEl.appendChild(s);
  };
  addTag(t.type, 'mtag-type');
  if (t.duration) addTag('⏱ ' + t.duration, 'mtag-dur');
  if (t.is_new)   addTag('New Release', 'mtag-new');

  // Plays (optimistic +1)
  document.getElementById('mPlays').textContent = (t.plays + 1).toLocaleString();

  // Lyrics
  const lyricsWrap = document.getElementById('mLyricsWrap');
  if (t.lyrics && t.lyrics.trim()) {
    lyricsWrap.style.display = '';
    document.getElementById('mLyrics').textContent = t.lyrics;
    document.getElementById('mLyricsBody').classList.remove('open');
    document.getElementById('lyricsArrow').textContent = '▼';
  } else {
    lyricsWrap.style.display = 'none';
  }

  // Open modal
  document.getElementById('playerModal').classList.add('open');
  document.body.style.overflow = 'hidden';

  // Persist play count
  fetch('video_blog.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'play_id=' + t.id
  }).catch(() => {});
}

function closeModal() {
  document.getElementById('playerModal').classList.remove('open');
  document.body.style.overflow = '';
  const wrap = document.getElementById('mMediaWrap');
  // Pause audio before removing (prevents ghost playback in some browsers)
  const audio = wrap.querySelector('audio');
  if (audio) { audio.pause(); audio.src = ''; }
  wrap.innerHTML = '';
  wrap.classList.remove('audio-mode');
}

function handleOverlayClick(e) {
  if (e.target === document.getElementById('playerModal')) closeModal();
}

// Keyboard close
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeModal();
});

function toggleLyrics() {
  const body  = document.getElementById('mLyricsBody');
  const arrow = document.getElementById('lyricsArrow');
  body.classList.toggle('open');
  arrow.textContent = body.classList.contains('open') ? '▲' : '▼';
}
</script>
</body>
</html>