<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/settings.php';

$d    = db();
$site = getAppearanceSettings($d);

$slug = trim($_GET['slug'] ?? '');

$post = null;
if ($slug) {
    $s = $d->prepare("SELECT * FROM posts WHERE slug=? AND status='published'");
    $s->execute([$slug]);
    $post = $s->fetch();
}

// Track view
if ($post) {
    trackView('/post/' . h($slug), 'post', (int)$post['id']);
}

function h2($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function tagBadges($tags) {
    $out = '';
    foreach (array_filter(array_map('trim', explode(',', $tags ?? ''))) as $tag) {
        $out .= '<span class="post-tag">' . htmlspecialchars($tag, ENT_QUOTES) . '</span>';
    }
    return $out;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $post ? h2($post['title']) . ' — ' . h2($site['logo_text']) : 'Post Not Found — ' . h2($site['logo_text']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<?= siteStyleTag($site) ?>
<link rel="stylesheet" href="/style.css">
<style>
.post-wrap { max-width: 760px; margin: 0 auto; padding: 100px 24px 80px; }
.post-kicker { font-size: .75rem; letter-spacing: .12em; text-transform: uppercase; color: var(--accent); margin-bottom: 16px; }
.post-kicker a { color: var(--accent); text-decoration: none; }
.post-kicker a:hover { text-decoration: underline; }
.post-title { font-size: clamp(1.8rem, 5vw, 3rem); font-weight: 800; line-height: 1.15; margin: 0 0 20px; }
.post-meta { display: flex; align-items: center; gap: 16px; font-size: .8rem; color: var(--muted); margin-bottom: 32px; flex-wrap: wrap; }
.post-tags { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 32px; }
.post-tag { background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 4px 12px; font-size: .72rem; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
.post-cover { width: 100%; aspect-ratio: 16/7; object-fit: cover; border-radius: 16px; margin-bottom: 40px; }
.post-cover-emoji { width: 100%; aspect-ratio: 16/7; border-radius: 16px; margin-bottom: 40px; display: flex; align-items: center; justify-content: center; font-size: 5rem; }
.post-body { font-size: 1.05rem; line-height: 1.8; color: var(--text); }
.post-body h2, .post-body h3 { margin-top: 2em; margin-bottom: .5em; font-weight: 700; }
.post-body p { margin: 0 0 1.4em; }
.post-body a { color: var(--accent); }
.post-body img { max-width: 100%; border-radius: 8px; margin: 1em 0; }
.post-body blockquote { border-left: 3px solid var(--accent); margin: 1.5em 0; padding: .5em 0 .5em 1.5em; color: var(--muted); font-style: italic; }
.post-back { display: inline-flex; align-items: center; gap: 8px; margin-top: 60px; color: var(--muted); font-size: .85rem; text-decoration: none; transition: color .2s; }
.post-back:hover { color: var(--text); }
.post-404 { text-align: center; padding: 120px 24px 80px; }
.post-404 h1 { font-size: 3rem; margin-bottom: 16px; }
.post-404 p { color: var(--muted); margin-bottom: 32px; }
</style>
</head>
<body>
<script>document.documentElement.setAttribute('data-theme','<?= h2($site['active_theme']) ?>');</script>

<!-- NAV -->
<nav class="nav" id="nav">
  <div class="nav-inner">
    <?= renderLogo($site, '/') ?>
    <ul class="nav-links" id="navLinks">
      <li><a href="/">Home</a></li>
      <li><a href="/music">Music</a></li>
      <li><a href="/videos">Videos</a></li>
      <li><a href="/blog" class="is-active">Journal</a></li>
    </ul>
    <button class="hamburger" id="hamburger" aria-label="Open menu">
      <span></span><span></span>
    </button>
  </div>
</nav>

<?php if ($post): ?>
<div class="post-wrap">
  <p class="post-kicker"><a href="/blog">← Journal</a></p>

  <h1 class="post-title"><?= h2($post['title']) ?></h1>

  <div class="post-meta">
    <?php if ($post['published_at']): ?>
    <time><?= date('F j, Y', strtotime($post['published_at'])) ?></time>
    <?php endif; ?>
    <?php if ($post['tags']): ?><span>·</span><?php endif; ?>
    <?php if ($post['tags']): echo tagBadges($post['tags']); endif; ?>
  </div>

  <?php if (!empty($post['cover_image'])): ?>
  <img src="/<?= h2($post['cover_image']) ?>" alt="<?= h2($post['title']) ?>" class="post-cover">
  <?php elseif (!empty($post['cover_emoji']) && $post['cover_emoji'] !== '-'): ?>
  <div class="post-cover-emoji" style="background:<?= h2($post['cover_color'] ?: 'linear-gradient(135deg,#1a1a2e,#2d1b69,#11998e)') ?>;">
    <?= h2($post['cover_emoji']) ?>
  </div>
  <?php endif; ?>

  <div class="post-body">
    <?= nl2br(h2($post['content'])) ?>
  </div>

  <a href="/blog" class="post-back">← Back to Journal</a>
</div>
<?php else: ?>
<div class="post-404">
  <h1>Post Not Found</h1>
  <p>The story you're looking for doesn't exist or has been unpublished.</p>
  <a href="/blog" class="btn-primary">← Back to Journal</a>
</div>
<?php endif; ?>

<!-- FOOTER -->
<footer class="footer" style="margin-top:80px">
  <div class="wrap">
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> <?= h2($site['logo_text']) ?>. All rights reserved.</span>
      <span>Made with ♥</span>
    </div>
  </div>
</footer>

<script>
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
</script>
</body>
</html>
