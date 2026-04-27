<?php


require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/settings.php';

$d    = db();
$site = getAppearanceSettings($d);

// Fetch featured/latest tracks (published, sorted)
$tracks = $d->query(
    "SELECT * FROM tracks WHERE is_published=1 ORDER BY is_featured DESC, sort_order ASC, id DESC LIMIT 4"
)->fetchAll();

// Fetch featured video (hero)
$featuredVideo = $d->query(
    "SELECT * FROM videos WHERE is_published=1 AND is_featured=1 LIMIT 1"
)->fetch();

// Fallback: just the most recent published video
if (!$featuredVideo) {
    $featuredVideo = $d->query(
        "SELECT * FROM videos WHERE is_published=1 ORDER BY id DESC LIMIT 1"
    )->fetch();
}

// Fetch grid videos (published, not featured, up to 5)
$gridVideos = $d->query(
    "SELECT * FROM videos WHERE is_published=1 AND is_featured=0 ORDER BY sort_order ASC, id DESC LIMIT 5"
)->fetchAll();

// If we have fewer than 5, pull more (even if featured)
if (count($gridVideos) < 5 && $featuredVideo) {
    $limit = 5 - count($gridVideos);
    $fid   = (int)$featuredVideo['id'];
    $extra = $d->query(
        "SELECT * FROM videos WHERE is_published=1 AND id != $fid ORDER BY sort_order ASC, id DESC LIMIT $limit"
    )->fetchAll();
    // merge without duplicates
    $existingIds = array_column($gridVideos, 'id');
    foreach ($extra as $v) {
        if (!in_array($v['id'], $existingIds)) $gridVideos[] = $v;
    }
}

// Fetch blog posts (published, sorted by date)
$posts = $d->query(
    "SELECT * FROM posts WHERE status='published' ORDER BY published_at DESC, id DESC LIMIT 4"
)->fetchAll();

// Stats (live)
$statTracks  = (int)$d->query("SELECT COUNT(*) FROM tracks WHERE is_published=1")->fetchColumn();
$statVideos  = (int)$d->query("SELECT COUNT(*) FROM videos WHERE is_published=1")->fetchColumn();
$statPosts   = (int)$d->query("SELECT COUNT(*) FROM posts  WHERE status='published'")->fetchColumn();

// Helper: escape output
function h2($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Helper: format play/view counts
function fmt($n) {
    if ($n >= 1000000) return round($n/1000000,1).'M';
    if ($n >= 1000)    return round($n/1000,1).'k';
    return $n;
}

// Helper: tag list from comma string
function tagBadges($tags) {
    $out = '';
    foreach (array_filter(array_map('trim', explode(',', $tags ?? ''))) as $tag) {
        $out .= '<span>' . htmlspecialchars($tag, ENT_QUOTES) . '</span>';
    }
    return $out;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RCL — Music, Blogs &amp; Streams</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<?= siteStyleTag($site) ?>
<link rel="stylesheet" href="style.css">
</head>
<body>
<script>document.documentElement.setAttribute('data-theme','<?= htmlspecialchars($site['active_theme']) ?>');</script>
<!-- NAV -->
<nav class="nav" id="nav">
  <div class="nav-inner">
    <?= renderLogo($site, 'index.php', 'logo') ?>
    <ul class="nav-links" id="navLinks">
      <li><a href="/" class="is-active">Home</a></li>
      <li><a href="/music">Music</a></li>
      <li><a href="/videos">Videos</a></li>
      <li><a href="/blog">Journal</a></li>
    </ul>
    <button class="hamburger" id="hamburger" aria-label="Open menu">
      <span></span><span></span>
    </button>
  </div>
</nav>

<!-- HERO -->
<section class="hero" id="hero">
  <div class="hero-img-wrap">
    <img src="images/rcl1.jpg" alt="RCL" class="hero-img">
    <div class="hero-vignette"></div>
  </div>

  <div class="hero-content">
    <h1 class="hero-title">
      <?= htmlspecialchars($site['hero_title_line1']) ?><br>
      <em><?= htmlspecialchars($site['hero_title_line2']) ?></em>
    </h1>
    <p class="hero-desc"><?= htmlspecialchars($site['hero_desc']) ?></p>
    <div class="hero-actions">
      <a href="/music" class="btn-primary">
        <span class="btn-icon">▶</span> Start Listening
      </a>
      <a href="/videos" class="btn-secondary">
        <span>ℹ</span> Watch Now
      </a>
    </div>
  </div>

  <div class="hero-bottom">
    <div class="hero-stats">
      <div class="hstat">
        <span class="hstat-n"><?= $statTracks ?>+</span>
        <span class="hstat-l">Songs</span>
      </div>
      <div class="hstat-div"></div>
      <div class="hstat">
        <span class="hstat-n"><?= $statVideos ?></span>
        <span class="hstat-l">Videos</span>
      </div>
      <div class="hstat-div"></div>
      <div class="hstat">
        <span class="hstat-n"><?= $statPosts ?>+</span>
        <span class="hstat-l">Blog Posts</span>
      </div>
    </div>
    <div class="hero-scroll">
      <span class="scroll-label">Scroll</span>
      <span class="scroll-line"></span>
    </div>
  </div>
</section>

<!-- PAGE BODY -->
<div class="page-body">

  <!-- TICKER -->
  <div class="ticker">
    <div class="ticker-track">
      <span>Singing Blogs</span><i>✦</i>
      <span>Video Streams</span><i>✦</i>
      <span>Live Music</span><i>✦</i>
      <span>Original Songs</span><i>✦</i>
      <span>Vocal Covers</span><i>✦</i>
      <span>Behind the Scenes</span><i>✦</i>
      <span>Singing Blogs</span><i>✦</i>
      <span>Video Streams</span><i>✦</i>
      <span>Live Music</span><i>✦</i>
      <span>Original Songs</span><i>✦</i>
      <span>Vocal Covers</span><i>✦</i>
      <span>Behind the Scenes</span><i>✦</i>
    </div>
  </div>

  <!-- ── MUSIC ── -->
  <section class="section" id="music">
    <div class="wrap">
      <header class="sec-head">
        <div class="sec-kicker"><span>01</span> Featured Music</div>
        <div class="sec-title-row">
          <h2 class="sec-title">Latest <em>Tracks</em></h2>
          <a href="/music" class="see-more">All tracks →</a>
        </div>
      </header>

      <?php if ($tracks): ?>
      <div class="music-grid">
        <?php foreach ($tracks as $i => $t):
          $grad  = h2($t['cover_color'] ?: 'linear-gradient(145deg,#7b0000,#c0392b,#8e44ad)');
          $note  = ['♪','♫','♩','♬'][$i % 4];
          $hero  = ($i === 0) ? ' mc-hero' : '';
          $newBadge = $t['is_new'] ? '<span class="mc-new">New</span>' : '';
          // Build JS-safe title/artist for onclick
          $jsTitle  = addslashes(h2($t['title']));
          $jsArtist = addslashes(h2($t['artist']));
          $typeLabel = ucfirst(h2($t['type'] ?? 'original'));
          $duration  = h2($t['duration'] ?? '');
          $plays     = fmt($t['plays'] ?? 0);

          // Audio source: file_path takes priority, then stream_url
          $audioSrc = '';
          $ytId     = h2($t['youtube_id'] ?? '');
          if (!empty($t['file_path']))      $audioSrc = '/' . h2($t['file_path']);
          elseif (!empty($t['youtube_id'])) $audioSrc = 'yt:' . $ytId;
          elseif (!empty($t['stream_url'])) $audioSrc = h2($t['stream_url']);
        ?>
        <div class="mc<?= $hero ?>" data-audio="<?= $audioSrc ?>">
          <div class="mc-art" style="--grad:<?= $grad ?>;">
            <?php if (!empty($t['cover_image'])): ?>
            <img src="/<?= h2($t['cover_image']) ?>" alt="<?= h2($t['title']) ?>" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:inherit;opacity:.85">
            <?php elseif (!empty($t['youtube_id'])): ?>
            <img src="https://img.youtube.com/vi/<?= h2($t['youtube_id']) ?>/hqdefault.jpg" alt="<?= h2($t['title']) ?>" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:inherit;opacity:.85">
            <?php else: ?>
            <span class="mc-note"><?= $note ?></span>
            <?php endif; ?>
            <button class="mc-btn" onclick="playTrack(this,'<?= $jsTitle ?>','<?= $jsArtist ?>')">▶</button>
            <?= $newBadge ?>
          </div>
          <div class="mc-info">
            <h3><?= h2($t['title']) ?></h3>
            <p><?= $typeLabel ?><?= $duration ? ' &nbsp;·&nbsp; ' . $duration : '' ?></p>
            <div class="mc-foot">
              <span><?= $plays ?> plays</span>
              <button onclick="toggleLike(this,<?= (int)$t['id'] ?>)" class="like-btn">♡</button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <p style="color:var(--muted);text-align:center;padding:40px 0">No tracks published yet.</p>
      <?php endif; ?>
    </div>
  </section>

  <!-- MINI PLAYER -->
  <!-- Hidden YouTube Audio Player -->
  <div id="ytPlayerWrap" style="position:fixed;width:0;height:0;overflow:hidden;pointer-events:none;opacity:0">
    <div id="yt-player"></div>
  </div>

  <div class="player" id="miniPlayer">
    <div class="player-left">
      <div class="player-disc" id="playerDisc">♪</div>
      <div>
        <div class="player-title" id="playerTitle">—</div>
        <div class="player-artist" id="playerArtist">—</div>
      </div>
    </div>
    <div class="player-ctrl">
      <button onclick="prevTrack()">⏮</button>
      <button class="player-playbtn" id="playerPlayBtn" onclick="togglePlay()">▶</button>
      <button onclick="nextTrack()">⏭</button>
    </div>
    <div class="player-prog">
      <div class="player-bar"><div class="player-fill" id="playerFill"></div></div>
      <span id="playerTime">0:00</span>
    </div>
    <!-- Close button -->
    <button class="player-close" onclick="closePlayer()" title="Close">✕</button>
  </div>

  <!-- ── VIDEOS ── -->
  <section class="section section-alt" id="videos">
    <div class="wrap">
      <header class="sec-head">
        <div class="sec-kicker"><span>02</span> Video Streams</div>
        <div class="sec-title-row">
          <h2 class="sec-title">Watch &amp; <em>Experience</em></h2>
          <a href="https://youtube.com/@9xbloc959" target="_blank" rel="noopener" class="see-more">All videos →</a>
        </div>
      </header>

      <?php if ($featuredVideo): ?>
      <div class="yt-featured">
        <div class="yt-lite" data-id="<?= h2($featuredVideo['youtube_id']) ?>" data-featured="true">
          <?php if ($featuredVideo['youtube_id']): ?>
          <iframe
            src="https://www.youtube.com/embed/<?= h2($featuredVideo['youtube_id']) ?>?autoplay=1&mute=1&controls=0&loop=1&playlist=<?= h2($featuredVideo['youtube_id']) ?>&showinfo=0&rel=0"
            frameborder="0"
            allow="autoplay; encrypted-media"
            class="yt-bg-frame"
            tabindex="-1"
            aria-hidden="true"
          ></iframe>
          <?php elseif ($featuredVideo['video_file']): ?>
          <video src="/<?= h2($featuredVideo['video_file']) ?>" autoplay muted loop class="yt-bg-frame"></video>
          <?php endif; ?>
          <div class="yt-overlay"></div>
          <button class="yt-play-btn" onclick="openVideo('<?= h2($featuredVideo['youtube_id']) ?>','<?= !empty($featuredVideo['video_file']) ? '/'.h2($featuredVideo['video_file']) : '' ?>')" aria-label="Play video">▶</button>
          <div class="yt-badge">Featured</div>
        </div>
        <div class="yt-featured-meta">
          <span class="vc-tag"><?= h2($featuredVideo['category'] ?? 'Latest') ?></span>
          <?php if ($featuredVideo['youtube_id']): ?>
          <a href="https://youtu.be/<?= h2($featuredVideo['youtube_id']) ?>" target="_blank" rel="noopener" class="yt-ch-link">@9xbloc959 on YouTube ↗</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($gridVideos): ?>
      <div class="yt-grid">
        <?php foreach ($gridVideos as $v): ?>
        <div class="yt-card">
          <div class="yt-lite" data-id="<?= h2($v['youtube_id']) ?>">
            <?php if ($v['youtube_id']): ?>
            <iframe
              src="https://www.youtube.com/embed/<?= h2($v['youtube_id']) ?>?autoplay=0&mute=1&controls=0&showinfo=0&rel=0"
              frameborder="0" allow="autoplay; encrypted-media"
              class="yt-bg-frame" tabindex="-1" aria-hidden="true"
            ></iframe>
            <?php elseif ($v['video_file']): ?>
            <video src="/<?= h2($v['video_file']) ?>" muted loop class="yt-bg-frame"></video>
            <?php endif; ?>
            <div class="yt-overlay"></div>
            <button class="yt-play-sm" onclick="openVideo('<?= h2($v['youtube_id']) ?>','<?= !empty($v['video_file']) ? '/'.h2($v['video_file']) : '' ?>')" aria-label="Play">▶</button>
          </div>
          <div class="yt-card-meta">
            <span class="vc-tag"><?= h2($v['category'] ?? 'Video') ?></span>
            <?php if ($v['title']): ?>
            <span class="vc-title" style="font-size:.75rem;color:var(--muted);display:block;margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h2($v['title']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="yt-sub-cta">
        <a href="<?= h2($site['social_youtube'] ?: 'https://youtube.com/@9xbloc959') ?>" target="_blank" rel="noopener" class="btn-yt">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2 31.5 31.5 0 0 0 0 12a31.5 31.5 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1A31.5 31.5 0 0 0 24 12a31.5 31.5 0 0 0-.5-5.8zM9.7 15.5V8.5l6.3 3.5-6.3 3.5z"/></svg>
          Subscribe on YouTube
        </a>
      </div>
    </div>
  </section>

  <!-- VIDEO MODAL -->
  <div class="modal" id="videoModal" onclick="closeVideo(event)">
    <div class="modal-box">
      <button class="modal-close" onclick="closeVideo()">✕</button>
      <iframe id="videoFrame" src="" allowfullscreen frameborder="0" allow="autoplay; encrypted-media"></iframe>
    </div>
  </div>

  <!-- ── BLOG ── -->
  <section class="section" id="blog">
    <div class="wrap">
      <header class="sec-head">
        <div class="sec-kicker"><span>03</span> Singing Journal</div>
        <div class="sec-title-row">
          <h2 class="sec-title">Stories &amp; <em>Reflections</em></h2>
          <a href="/blog" class="see-more">All posts →</a>
        </div>
      </header>

      <?php if ($posts): ?>
      <div class="blog-grid">
        <?php foreach ($posts as $i => $p):
          $grad  = h2($p['cover_color'] ?: 'linear-gradient(135deg,#1a1a2e,#2d1b69,#11998e)');
          $emoji = h2($p['cover_emoji'] ?? '-');
          $main  = ($i === 0) ? ' bc-main' : '';
          $imgSm = ($i > 0) ? ' bc-img-sm' : '';
          $date  = $p['published_at'] ? date('F j, Y', strtotime($p['published_at'])) : '';
          $slug  = h2($p['slug'] ?? '');
        ?>
        <article class="bc<?= $main ?>">
          <?php if (!empty($p['cover_image'])): ?>
          <div class="bc-img<?= $imgSm ?>">
            <img src="/<?= h2($p['cover_image']) ?>" alt="<?= h2($p['title']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:inherit">
          </div>
          <?php else: ?>
          <div class="bc-img<?= $imgSm ?>" style="--grad:<?= $grad ?>;"><span><?= $emoji ?></span></div>
          <?php endif; ?>
          <div class="bc-body">
            <?php if ($p['tags']): ?>
            <div class="bc-tags"><?= tagBadges($p['tags']) ?></div>
            <?php endif; ?>
            <h3><?= h2($p['title']) ?></h3>
            <?php if ($i === 0 && $p['excerpt']): ?>
            <p><?= h2($p['excerpt']) ?></p>
            <?php endif; ?>
            <div class="bc-foot">
              <?php if ($date): ?><time><?= $date ?></time><?php endif; ?>
              <a href="post.php?slug=<?= $slug ?>">Read<?= $i === 0 ? ' story' : '' ?> →</a>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <p style="color:var(--muted);text-align:center;padding:40px 0">No posts published yet.</p>
      <?php endif; ?>
    </div>
  </section>

  <!-- CTA -->
  <section class="cta-section" id="contact">
    <div class="wrap">
      <div class="cta-inner">
        <div class="cta-left">
          <p class="cta-kicker">Stay in the loop</p>
          <h2 class="cta-title">
            <?= htmlspecialchars($site['cta_title_line1']) ?><br>
            <em><?= htmlspecialchars($site['cta_title_line2']) ?></em>
          </h2>
          <p class="cta-sub"><?= htmlspecialchars($site['cta_sub']) ?></p>
        </div>
        <div class="cta-right">
          <form class="cta-form" id="subForm" method="POST" action="/subscribe">
            <label for="ctaEmail">Email address</label>
            <div class="cta-row">
              <input type="email" id="ctaEmail" name="email" placeholder="you@example.com" required>
              <button type="submit">Subscribe</button>
            </div>
            <small>No spam. Unsubscribe anytime.</small>
          </form>
          <?php if (!empty($_GET['subscribed'])): ?>
          <p class="cta-thanks">✓ You're in! Welcome to RCL. 🎶</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="wrap">
      <div class="footer-inner">
        <div class="footer-brand">
          <div class="footer-logo">
            <?= htmlspecialchars($site['logo_text']) ?><span>.</span>
          </div>
          <p><?= $site['footer_tagline'] ?></p>
          <div class="socials">
            <?= renderSocials($site) ?>
          </div>
        </div>
        <div class="footer-nav">
          <div class="footer-col">
            <h4>Navigate</h4>
            <a href="/">Home</a>
            <a href="/music">Music</a>
            <a href="/videos">Videos</a>
            <a href="/blog">Journal</a>
          </div>
          <div class="footer-col">
            <h4>Content</h4>
            <a href="#">Original Songs</a>
            <a href="#">Vocal Covers</a>
            <a href="#">Live Sessions</a>
            <a href="#">Tutorials</a>
          </div>
          <div class="footer-col">
            <h4>Connect</h4>
            <a href="#">Collaborations</a>
            <a href="#">Press &amp; Media</a>
            <a href="#">Contact</a>
          </div>
        </div>
      </div>
      <div class="footer-bottom">
        <span>© <?= date('Y') ?> <?= htmlspecialchars($site['logo_text']) ?>. All rights reserved.</span>
        <span>Made with ♥</span>
      </div>
    </div>
  </footer>

</div>

<script>
const tracks = <?= json_encode(array_map(function($t) {
    $src = '';
    if (!empty($t['file_path']))      $src = '/' . $t['file_path'];
    elseif (!empty($t['youtube_id'])) $src = 'yt:' . $t['youtube_id'];
    elseif (!empty($t['stream_url'])) $src = $t['stream_url'];
    return [
        'id'    => $t['id'],
        'title' => $t['title'],
        'artist'=> $t['artist'],
        'src'   => $src,
        'ytId'  => $t['youtube_id'] ?? '',
        'dur'   => $t['duration'] ?? '',
    ];
}, $tracks), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

// ── Nav scroll ──
const nav = document.getElementById('nav');
window.addEventListener('scroll', () => {
  nav.classList.toggle('scrolled', window.scrollY > 80);
});

const hamburger = document.getElementById('hamburger');
const navLinks  = document.getElementById('navLinks');
hamburger.addEventListener('click', () => {
  navLinks.classList.toggle('open');
  hamburger.classList.toggle('open');
});
navLinks.querySelectorAll('a').forEach(a => {
  a.addEventListener('click', () => {
    navLinks.classList.remove('open');
    hamburger.classList.remove('open');
  });
});

// ── Intersection observer ──
const obs = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('in-view'); });
}, { threshold: 0.06 });
document.querySelectorAll('.section, .ticker, .cta-section').forEach(el => obs.observe(el));

// ── VIDEO MODAL ──
function openVideo(ytId, fileUrl) {
  let src = '';
  if (ytId)         src = `https://www.youtube.com/embed/${ytId}?autoplay=1&rel=0`;
  else if (fileUrl) src = fileUrl;
  if (!src) return;
  document.getElementById('videoFrame').src = src;
  document.getElementById('videoModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeVideo(e) {
  if (e && !e.target.classList.contains('modal') && !e.target.classList.contains('modal-close')) return;
  document.getElementById('videoFrame').src = '';
  document.getElementById('videoModal').classList.remove('open');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeVideo({ target: { classList: { contains: () => true } } });
});

// ── YOUTUBE IFRAME API ──
let ytPlayer = null, ytReady = false, ytPendingId = null;

const ytScript = document.createElement('script');
ytScript.src = 'https://www.youtube.com/iframe_api';
document.head.appendChild(ytScript);

function onYouTubeIframeAPIReady() {
  ytPlayer = new YT.Player('yt-player', {
    height: '1', width: '1',
    playerVars: { autoplay: 0, controls: 0, disablekb: 1 },
    events: {
      onReady: () => {
        ytReady = true;
        if (ytPendingId) { ytPlayer.loadVideoById(ytPendingId); ytPendingId = null; }
      },
      onStateChange: (e) => {
        if (e.data === YT.PlayerState.ENDED) {
          playing = false;
          document.getElementById('playerPlayBtn').textContent = '▶';
          document.getElementById('miniPlayer').classList.remove('playing');
          clearInterval(ytTimer);
        }
        if (e.data === YT.PlayerState.PLAYING) startYtProgress();
      }
    }
  });
}

let ytTimer = null;
function startYtProgress() {
  clearInterval(ytTimer);
  ytTimer = setInterval(() => {
    if (!ytPlayer || !ytReady) return;
    const cur = ytPlayer.getCurrentTime() || 0;
    const dur = ytPlayer.getDuration()    || 0;
    if (dur > 0) {
      document.getElementById('playerFill').style.width = (cur / dur * 100) + '%';
      document.getElementById('playerTime').textContent =
        `${Math.floor(cur/60)}:${String(Math.floor(cur%60)).padStart(2,'0')}`;
    }
  }, 500);
}

// ── MINI PLAYER STATE ──
let playing = false, trackIdx = 0, progress = 0, timer = null;
let audio = null;
let currentType = null; // 'audio' | 'yt'

function playTrack(btn, title, artist) {
  document.querySelectorAll('.mc-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  trackIdx = tracks.findIndex(t => t.title === title);
  if (trackIdx === -1) trackIdx = 0;
  loadAndPlay(trackIdx, title, artist);
}

function loadAndPlay(idx, title, artist) {
  const t = tracks[idx] || {};
  title  = title  || t.title  || '—';
  artist = artist || t.artist || '—';

  document.getElementById('playerTitle').textContent  = title;
  document.getElementById('playerArtist').textContent = artist;
  document.getElementById('miniPlayer').classList.add('visible');
  document.getElementById('playerFill').style.width = '0%';
  document.getElementById('playerTime').textContent = '0:00';

  stopAll();

  if (t.src && t.src.startsWith('yt:')) {
    currentType = 'yt';
    const ytId = t.src.replace('yt:', '');
    if (ytReady && ytPlayer) {
      ytPlayer.loadVideoById(ytId);
    } else {
      ytPendingId = ytId;
    }
  } else if (t.src) {
    currentType = 'audio';
    audio = new Audio(t.src);
    audio.addEventListener('timeupdate', () => {
      if (!audio.duration) return;
      const pct = (audio.currentTime / audio.duration) * 100;
      document.getElementById('playerFill').style.width = pct + '%';
      const s = Math.floor(audio.currentTime);
      document.getElementById('playerTime').textContent =
        `${Math.floor(s/60)}:${String(s%60).padStart(2,'0')}`;
    });
    audio.addEventListener('ended', () => {
      playing = false;
      document.getElementById('playerPlayBtn').textContent = '▶';
      document.getElementById('miniPlayer').classList.remove('playing');
    });
    audio.play().catch(() => {});
  } else {
    currentType = null;
    progress = 0;
    timer = setInterval(() => {
      progress = Math.min(progress + 0.35, 100);
      document.getElementById('playerFill').style.width = progress + '%';
      const s = Math.floor(progress * 2.4);
      document.getElementById('playerTime').textContent =
        `${Math.floor(s/60)}:${String(s%60).padStart(2,'0')}`;
      if (progress >= 100) clearInterval(timer);
    }, 500);
  }

  playing = true;
  document.getElementById('playerPlayBtn').textContent = '⏸';
  document.getElementById('miniPlayer').classList.add('playing');
}

function stopAll() {
  if (audio) { audio.pause(); audio = null; }
  if (ytReady && ytPlayer) { ytPlayer.stopVideo(); }
  clearInterval(timer);
  clearInterval(ytTimer);
}

function togglePlay() {
  if (!document.getElementById('miniPlayer').classList.contains('visible')) return;
  playing = !playing;
  document.getElementById('playerPlayBtn').textContent = playing ? '⏸' : '▶';
  document.getElementById('miniPlayer').classList.toggle('playing', playing);

  if (currentType === 'yt' && ytReady && ytPlayer) {
    playing ? ytPlayer.playVideo() : ytPlayer.pauseVideo();
  } else if (currentType === 'audio' && audio) {
    playing ? audio.play() : audio.pause();
  } else {
    if (playing) {
      timer = setInterval(() => {
        progress = Math.min(progress + 0.35, 100);
        document.getElementById('playerFill').style.width = progress + '%';
      }, 500);
    } else clearInterval(timer);
  }
}

function prevTrack() {
  trackIdx = (trackIdx - 1 + tracks.length) % tracks.length;
  loadAndPlay(trackIdx);
  updateActiveBtn();
}
function nextTrack() {
  trackIdx = (trackIdx + 1) % tracks.length;
  loadAndPlay(trackIdx);
  updateActiveBtn();
}
function updateActiveBtn() {
  const cards = document.querySelectorAll('.mc-btn');
  cards.forEach((b, i) => b.classList.toggle('active', i === trackIdx));
}

function closePlayer() {
  stopAll();
  playing = false;
  progress = 0;
  currentType = null;
  document.getElementById('miniPlayer').classList.remove('visible', 'playing');
  document.getElementById('playerFill').style.width = '0%';
  document.getElementById('playerTime').textContent = '0:00';
  document.getElementById('playerTitle').textContent = '—';
  document.getElementById('playerArtist').textContent = '—';
  document.getElementById('playerPlayBtn').textContent = '▶';
  document.querySelectorAll('.mc-btn').forEach(b => b.classList.remove('active'));
}

function toggleLike(btn, trackId) {
  btn.classList.toggle('liked');
  btn.textContent = btn.classList.contains('liked') ? '♥' : '♡';
  if (trackId) {
    fetch('api/like.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: trackId, action: btn.classList.contains('liked') ? 'like' : 'unlike' })
    }).catch(() => {});
  }
}
</script>
</body>
</html>