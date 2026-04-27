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
<style>
  /* ============================================================
   RCL — style.css  |  Netflix-inspired  |  Fully Responsive
   NOTE: Theme :root variables (--bg, --accent, --ff, etc.)
         are injected by siteStyleTag() in includes/settings.php
         Do NOT hardcode colors or fonts here — use var() only.

   COMPONENT PALETTE:
   --c-nav-*       Navigation bar (universal, not theme-affected)
   --c-player-*    Mini music player
   --c-footer-*    Footer
   --c-hero-*      Hero section overlays / vignette tint
   --c-ticker-*    Ticker / announcement bar
   These are injected by siteStyleTag() from the components palette
   and override the theme palette for those specific elements.
   ============================================================ */

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── UI-only variables (not theme-controlled) ── */
:root {
  --border:    rgba(255,255,255,0.07);
  --border2:   rgba(255,255,255,0.15);
  --dim:       rgba(255,255,255,0.05);

  --ff-mono:  'SF Mono', 'Fira Code', monospace;

  --ease:     cubic-bezier(0.4, 0, 0.2, 1);
  --ease-out: cubic-bezier(0, 0, 0.2, 1);
  --t:        0.2s;

  --nav-h: 64px;
  --max-w: 1440px;
  --pad-x: clamp(16px, 4vw, 60px);

  /* ── Component palette fallbacks (overridden by siteStyleTag) ── */
  --c-nav-bg:          rgba(0,0,0,0.9);
  --c-nav-bg-scrolled: #1a1a1a;
  --c-nav-text:        #ffffff;
  --c-nav-muted:       #b3b3b3;
  --c-nav-accent:      #e50914;
  --c-nav-border:      rgba(255,255,255,0.07);
  --c-nav-mobile-bg:   rgba(0,0,0,0.98);

  --c-player-bg:       #1f1f1f;
  --c-player-border:   rgba(255,255,255,0.1);
  --c-player-text:     #ffffff;
  --c-player-muted:    #b3b3b3;
  --c-player-accent:   #e50914;
  --c-player-bar-bg:   rgba(255,255,255,0.1);
  --c-player-shadow:   rgba(0,0,0,0.8);

  --c-footer-bg:       #000000;
  --c-footer-border:   rgba(255,255,255,0.07);
  --c-footer-text:     #ffffff;
  --c-footer-muted:    #b3b3b3;
  --c-footer-accent:   #e50914;
  --c-footer-link:     rgba(255,255,255,0.45);

  --c-hero-vignette-l: rgba(0,0,0,0.82);
  --c-hero-vignette-r: rgba(0,0,0,0.1);
  --c-hero-vignette-b: rgba(0,0,0,0.95);
  --c-hero-vignette-t: rgba(0,0,0,0.65);
  --c-hero-text:       #ffffff;
  --c-hero-desc:       rgba(255,255,255,0.7);
  --c-hero-stat-label: rgba(255,255,255,0.55);
  --c-hero-divider:    rgba(255,255,255,0.15);
  --c-hero-scroll:     rgba(255,255,255,0.5);
  --c-hero-accent:     #e50914;

  --c-ticker-bg:       #e50914;
  --c-ticker-text:     #ffffff;
  --c-ticker-sep:      rgba(255,255,255,0.4);
}

html { scroll-behavior: smooth; }

body {
  font-family: var(--ff, 'Inter', 'Helvetica Neue', Arial, sans-serif);
  background: var(--bg3, #000000);
  color: var(--text, #ffffff);
  overflow-x: hidden;
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
}

::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-track { background: var(--bg3, #000000); }
::-webkit-scrollbar-thumb { background: var(--accent, #e50914); border-radius: 0; }

.wrap {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 0 var(--pad-x);
}

/* ── NAV — uses component palette ── */
.nav {
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 1000;
  height: var(--nav-h);
  display: flex;
  align-items: center;
  padding: 0 var(--pad-x);
background: linear-gradient(
  to bottom,
  var(--c-nav-bg) 0%,
  var(--c-nav-bg) 100%
);
  transition: background var(--t) var(--ease);
  border-bottom: 1px solid transparent;
}
.nav.scrolled {
  background: var(--c-nav-bg-scrolled);
  border-bottom-color: var(--c-nav-border);
}

.nav-inner {
  width: 100%;
  max-width: var(--max-w);
  margin: 0 auto;
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.logo {
  font-size: clamp(1.25rem, 4vw, 1.75rem);
  font-weight: 900;
  color: var(--c-nav-accent);
  text-decoration: none;
  letter-spacing: -0.03em;
  text-transform: uppercase;
  flex-shrink: 0;
  line-height: 1;
}
.logo span { color: var(--c-nav-text); }

.nav-links {
  list-style: none;
  display: flex;
  align-items: center;
  gap: 1.5rem;
  margin-left: auto;
}
.nav-links a {
  font-size: 0.82rem;
  font-weight: 500;
  color: var(--c-nav-muted);
  text-decoration: none;
  transition: color var(--t);
  white-space: nowrap;
}
.nav-links a:hover,
.nav-links a.is-active { color: var(--c-nav-text); }

.hamburger {
  display: none;
  flex-direction: column;
  gap: 5px;
  background: none;
  border: none;
  cursor: pointer;
  padding: 6px;
  margin-left: auto;
  flex-shrink: 0;
}
.hamburger span {
  display: block;
  width: 22px; height: 2px;
  background: var(--c-nav-text);
  border-radius: 1px;
  transition: var(--t) var(--ease);
  transform-origin: center;
}
.hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.hamburger.open span:nth-child(2) { transform: translateY(-7px) rotate(-45deg); }

/* ── HERO — uses component palette ── */
.hero {
  position: fixed;
  top: 0; left: 0; right: 0;
  height: 100vh;
  z-index: 0;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  overflow: hidden;
}
.hero-img-wrap { position: absolute; inset: 0; z-index: 0; }
.hero-img { width: 100%; height: 100%; object-fit: cover; object-position: center top; }
.hero-vignette {
  position: absolute; inset: 0;
  background:
    linear-gradient(to right, var(--c-hero-vignette-l) 0%, rgba(0,0,0,0.4) 55%, var(--c-hero-vignette-r) 100%),
    linear-gradient(to top,   var(--c-hero-vignette-b) 0%, rgba(0,0,0,0.0) 50%),
    linear-gradient(to bottom,var(--c-hero-vignette-t) 0%, rgba(0,0,0,0.0) 25%);
}

.hero-content {
  position: relative; z-index: 2;
  padding: 0 var(--pad-x);
  max-width: 680px; width: 100%;
}
.hero-eyebrow {
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 1rem;
  animation: fade-up 0.7s 0.1s var(--ease) both;
}
.eyebrow-rule { display: block; width: 3px; height: 16px; background: var(--c-hero-accent); border-radius: 1px; flex-shrink: 0; }
.eyebrow-text { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--c-hero-accent); }

.hero-title {
  font-weight: 900;
  font-size: clamp(2rem, 7vw, 6rem);
  line-height: 1.0;
  letter-spacing: -0.03em;
  color: var(--c-hero-text);
  margin-bottom: 1.1rem;
  text-transform: uppercase;
  animation: fade-up 0.7s 0.2s var(--ease) both;
}
.hero-title em { font-style: normal; color: var(--c-hero-accent); }

.hero-desc {
  font-size: clamp(0.85rem, 2vw, 1.05rem);
  color: var(--c-hero-desc);
  line-height: 1.6;
  margin-bottom: 1.6rem;
  animation: fade-up 0.7s 0.3s var(--ease) both;
}

.hero-actions {
  display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;
  animation: fade-up 0.7s 0.4s var(--ease) both;
}

/* ── BUTTONS ── */
.btn-primary {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--accent, #e50914);
  color: #ffffff;
  font-size: clamp(0.76rem, 2vw, 0.9rem); font-weight: 700;
  letter-spacing: 0.02em; text-transform: uppercase; text-decoration: none;
  padding: clamp(10px, 2vw, 13px) clamp(16px, 3vw, 28px);
  border-radius: 3px; transition: background var(--t), transform var(--t); white-space: nowrap;
  border: none; cursor: pointer;
}
.btn-primary:hover { background: var(--accent-lt, #ff2b39); transform: scale(1.02); color: #ffffff; }

.btn-secondary {
  display: inline-flex; align-items: center; gap: 8px;
  font-size: clamp(0.76rem, 2vw, 0.9rem); font-weight: 700;
  letter-spacing: 0.02em; text-transform: uppercase;
  color: var(--text, #ffffff); text-decoration: none;
  background: rgba(255,255,255,0.18);
  padding: clamp(10px, 2vw, 13px) clamp(16px, 3vw, 28px);
  border-radius: 3px; transition: background var(--t); white-space: nowrap;
  border: none; cursor: pointer;
}
.btn-secondary:hover { background: rgba(255,255,255,0.28); }

/* ── HERO BOTTOM — uses component palette ── */
.hero-bottom {
  position: relative; z-index: 2;
  display: flex; align-items: flex-end; justify-content: space-between;
  padding: 1.5rem var(--pad-x);
  animation: fade-up 0.7s 0.55s var(--ease) both;
}
.hero-stats { display: flex; align-items: center; gap: clamp(1rem, 3vw, 2rem); flex-wrap: wrap; }
.hstat { display: flex; flex-direction: column; gap: 2px; }
.hstat-n {
  font-size: clamp(1.3rem, 3.5vw, 2rem);
  font-weight: 800;
  line-height: 1;
  color: var(--c-hero-text);
  letter-spacing: -0.03em;
}
.hstat-l {
  font-size: 0.6rem;
  font-weight: 600;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--c-hero-stat-label);
}
.hstat-div { width: 1px; height: 30px; background: var(--c-hero-divider); }

.hero-scroll { display: flex; align-items: center; gap: 10px; }
.scroll-label {
  font-size: 0.6rem;
  font-weight: 600;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--c-hero-scroll);
  writing-mode: vertical-rl;
}
.scroll-line { display: block; width: 1px; height: 44px; background: linear-gradient(to bottom, var(--c-hero-accent), transparent); animation: scroll-pulse 2s ease-in-out infinite; }
@keyframes scroll-pulse { 0%,100%{ opacity:.5; transform:scaleY(1); } 50%{ opacity:1; transform:scaleY(.6); } }

/* ── PAGE BODY ── */
.page-body { position: relative; z-index: 10; margin-top: 100vh; background: var(--bg, #141414); box-shadow: 0 -2px 40px rgba(0,0,0,0.9); }

/* ── TICKER — uses component palette ── */
.ticker {
  background: var(--c-ticker-bg); overflow: hidden; white-space: nowrap; padding: 10px 0;
  opacity: 0; transform: translateY(10px);
  transition: opacity 0.5s var(--ease), transform 0.5s var(--ease);
}
.ticker.in-view { opacity: 1; transform: translateY(0); }
.ticker-track { display: inline-flex; align-items: center; gap: 2rem; animation: ticker-move 22s linear infinite; }
.ticker-track span { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--c-ticker-text); }
.ticker-track i { font-style: normal; color: var(--c-ticker-sep); font-size: 0.5rem; }
@keyframes ticker-move { from{ transform:translateX(0); } to{ transform:translateX(-50%); } }

/* ── SECTIONS ── */
.section {
  padding: clamp(44px, 8vw, 100px) 0;
  opacity: 0; transform: translateY(20px);
  transition: opacity 0.6s var(--ease), transform 0.6s var(--ease);
}
.section.in-view { opacity: 1; transform: translateY(0); }
.section-alt { background: var(--bg2, #1a1a1a); }

.sec-head { margin-bottom: clamp(20px, 4vw, 48px); }
.sec-kicker { display: flex; align-items: center; gap: 10px; margin-bottom: 0.5rem; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--muted, #b3b3b3); }
.sec-kicker span { font-size: 0.58rem; color: var(--accent, #e50914); background: rgba(229,9,20,0.12); padding: 2px 7px; border-radius: 2px; }
.sec-title-row { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.sec-title { font-size: clamp(1.5rem, 4vw, 3rem); font-weight: 800; line-height: 1.05; color: var(--text, #ffffff); letter-spacing: -0.03em; text-transform: uppercase; }
.sec-title em { font-style: normal; color: var(--accent, #e50914); }
.see-more { font-size: 0.68rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted, #b3b3b3); text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.15); padding-bottom: 1px; white-space: nowrap; flex-shrink: 0; transition: color var(--t), border-color var(--t); }
.see-more:hover { color: var(--text, #ffffff); border-color: var(--text, #ffffff); }

/* ── MUSIC GRID ── */
.music-grid {
  display: grid;
  grid-template-columns: 1.6fr 1fr 1fr 1fr;
  gap: 1px;
  background: var(--border);
  border: 1px solid var(--border);
  border-radius: 6px;
  overflow: hidden;
}
.mc { background: var(--card, #1f1f1f); display: flex; flex-direction: column; transition: background var(--t); min-width: 0; }
.mc:hover { background: #272727; }
.mc-art { background: var(--grad, linear-gradient(135deg, #e50914 0%, #7b2ff7 100%)); aspect-ratio: 1; position: relative; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.mc-hero .mc-art { aspect-ratio: unset; height: clamp(150px, 18vw, 240px); }
.mc-note { font-size: clamp(2.8rem, 5vw, 4.5rem); opacity: 0.15; line-height: 1; transition: var(--t); user-select: none; }
.mc:hover .mc-note { opacity: 0.06; transform: scale(1.1); }
.mc-btn {
  position: absolute; width: 46px; height: 46px; border-radius: 50%; border: none;
  background: var(--accent, #e50914); color: #fff; font-size: 0.9rem; cursor: pointer;
  opacity: 0; transform: scale(0.75);
  transition: opacity var(--t), transform var(--t);
  box-shadow: 0 4px 20px rgba(229,9,20,0.4);
  display: flex; align-items: center; justify-content: center; padding-left: 3px;
}
.mc:hover .mc-btn { opacity: 1; transform: scale(1); }
.mc-btn.active { opacity: 1; transform: scale(1); }
.mc-new { position: absolute; top: 10px; left: 10px; background: var(--accent, #e50914); color: #fff; font-size: 0.58rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; padding: 3px 8px; border-radius: 2px; }
.mc-info { padding: clamp(10px, 2vw, 16px); flex: 1; display: flex; flex-direction: column; gap: 4px; }
.mc-info h3 { font-size: clamp(0.78rem, 1.5vw, 0.95rem); font-weight: 700; color: var(--text, #ffffff); line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mc-info p { font-size: 0.65rem; color: var(--muted, #b3b3b3); }
.mc-foot { display: flex; align-items: center; justify-content: space-between; margin-top: auto; padding-top: 8px; }
.mc-foot span { font-size: 0.62rem; color: var(--muted, #b3b3b3); }
.like-btn { background: none; border: none; cursor: pointer; color: var(--muted, #b3b3b3); font-size: 1rem; transition: color var(--t), transform var(--t); line-height: 1; padding: 0; flex-shrink: 0; }
.like-btn:hover { color: var(--accent, #e50914); transform: scale(1.2); }
.like-btn.liked { color: var(--accent, #e50914); }

/* ── MINI PLAYER — uses component palette ── */
.player {
  position: fixed; bottom: -100px; left: 50%; transform: translateX(-50%);
  z-index: 900; width: min(540px, calc(100vw - 24px));
  background: var(--c-player-bg); border: 1px solid var(--c-player-border); border-radius: 4px;
  padding: 10px 16px; display: flex; align-items: center; gap: 0.85rem;
  box-shadow: 0 8px 40px var(--c-player-shadow); transition: bottom 0.4s var(--ease-out);
}
.player.visible { bottom: 16px; }
.player-disc { width: 34px; height: 34px; border-radius: 50%; background: var(--c-player-accent); display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0; animation: spin 4s linear infinite paused; }
.player.playing .player-disc { animation-play-state: running; }
@keyframes spin { to{ transform:rotate(360deg); } }
.player-left { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
.player-title { font-size: 0.8rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--c-player-text); }
.player-artist { font-size: 0.65rem; color: var(--c-player-muted); }
.player-ctrl { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.player-ctrl button { background: none; border: none; color: var(--c-player-muted); font-size: 0.88rem; cursor: pointer; transition: color var(--t); padding: 4px; }
.player-ctrl button:hover { color: var(--c-player-text); }
.player-playbtn { width: 32px; height: 32px; border-radius: 50% !important; background: var(--c-player-accent) !important; color: #fff !important; font-size: 0.75rem !important; display: flex; align-items: center; justify-content: center; padding-left: 2px !important; }
.player-prog { display: flex; align-items: center; gap: 8px; width: 100px; flex-shrink: 0; }
.player-bar { flex: 1; height: 2px; background: var(--c-player-bar-bg); border-radius: 1px; overflow: hidden; }
.player-fill { height: 100%; background: var(--c-player-accent); width: 0%; transition: width 0.5s linear; }
.player-prog span { font-size: 0.6rem; color: var(--c-player-muted); white-space: nowrap; }
.player-close {
  background: none; border: none; color: var(--c-player-muted);
  font-size: 1rem; cursor: pointer; padding: 4px 8px; margin-left: 8px;
  border-radius: 50%; transition: color .2s, background .2s; flex-shrink: 0;
}
.player-close:hover { color: var(--c-player-text); background: rgba(255,255,255,0.12); }

/* ── VIDEO GRID ── */
.video-grid { display: grid; grid-template-columns: 1.6fr 1fr; grid-template-rows: auto auto; gap: 10px; }
.vc-main { grid-row: span 2; }
.vc { border-radius: 4px; overflow: hidden; background: var(--card, #1f1f1f); border: 1px solid var(--border); min-width: 0; }
.vc-thumb { position: relative; aspect-ratio: 16/9; display: flex; align-items: center; justify-content: center; cursor: pointer; overflow: hidden; }
.vc-play { width: 50px; height: 50px; border-radius: 50%; background: var(--accent, #e50914); border: none; color: #fff; font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; padding-left: 3px; transition: transform var(--t), background var(--t); }
.vc-thumb:hover .vc-play { transform: scale(1.1); background: var(--accent-lt, #ff2b39); }
.vc-dur { position: absolute; bottom: 8px; right: 8px; background: rgba(0,0,0,0.8); font-size: 0.62rem; font-weight: 600; color: #fff; padding: 2px 6px; border-radius: 2px; }
.vc-name { position: absolute; bottom: 0; left: 0; right: 0; padding: 28px 12px 10px; background: linear-gradient(transparent, rgba(0,0,0,0.9)); font-size: 0.8rem; font-weight: 600; line-height: 1.3; }
.vc-meta { padding: 8px 12px; display: flex; align-items: center; gap: 8px; font-size: 0.62rem; color: var(--muted, #b3b3b3); }
.vc-tag { background: rgba(229,9,20,0.15); color: var(--accent, #e50914); font-weight: 700; padding: 2px 8px; border-radius: 2px; font-size: 0.58rem; letter-spacing: 0.08em; text-transform: uppercase; white-space: nowrap; }

/* ── MODAL ── */
.modal { display: none; position: fixed; inset: 0; z-index: 2000; background: rgba(0,0,0,0.95); align-items: center; justify-content: center; padding: 16px; }
.modal.open { display: flex; }
.modal-box { position: relative; width: min(900px, 100%); aspect-ratio: 16/9; }
.modal-box iframe { width: 100%; height: 100%; border-radius: 4px; }
.modal-close { position: absolute; top: -40px; right: 0; background: none; border: none; color: rgba(255,255,255,0.7); font-size: 1.4rem; cursor: pointer; padding: 4px 8px; transition: color var(--t); }
.modal-close:hover { color: #fff; }

/* ── BLOG GRID ── */
.blog-grid { display: grid; grid-template-columns: 1.5fr 1fr 1fr; grid-template-rows: auto auto; gap: 10px; }
.bc-main { grid-row: span 2; display: flex; flex-direction: column; }
.bc { background: var(--card, #1f1f1f); border: 1px solid var(--border); border-radius: 4px; overflow: hidden; display: flex; flex-direction: column; transition: transform var(--t), border-color var(--t); min-width: 0; }
.bc:hover { transform: translateY(-3px); border-color: rgba(229,9,20,0.3); }
.bc-img { background: var(--grad, linear-gradient(135deg, #e50914 0%, #7b2ff7 100%)); height: clamp(110px, 16vw, 180px); display: flex; align-items: center; justify-content: center; font-size: 2.2rem; flex-shrink: 0; }
.bc-img-sm { height: clamp(75px, 11vw, 110px); font-size: 1.75rem; }
.bc-body { padding: clamp(12px, 2vw, 18px); flex: 1; display: flex; flex-direction: column; }
.bc-tags { display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 8px; }
.bc-tags span { font-size: 0.58rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--muted, #b3b3b3); background: var(--dim); border: 1px solid var(--border2); padding: 2px 7px; border-radius: 2px; }
.bc-body h3 { font-size: clamp(0.82rem, 1.5vw, 1.05rem); font-weight: 700; line-height: 1.35; color: var(--text, #ffffff); margin-bottom: 6px; }
.bc-body p { font-size: 0.8rem; color: var(--muted, #b3b3b3); line-height: 1.6; flex: 1; }
.bc-foot { display: flex; align-items: center; justify-content: space-between; margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--border); gap: 8px; }
.bc-foot time { font-size: 0.6rem; color: var(--muted, #b3b3b3); white-space: nowrap; }
.bc-foot a { font-size: 0.63rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--accent, #e50914); text-decoration: none; white-space: nowrap; transition: color var(--t); }
.bc-foot a:hover { color: var(--accent-lt, #ff2b39); }

/* ── CTA ── */
.cta-section {
  padding: clamp(48px, 9vw, 120px) 0;
  background: var(--bg3, #000000); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);
  opacity: 0; transform: translateY(20px);
  transition: opacity 0.6s var(--ease), transform 0.6s var(--ease);
}
.cta-section.in-view { opacity: 1; transform: translateY(0); }
.cta-inner { display: grid; grid-template-columns: 1fr 1fr; gap: clamp(28px, 6vw, 80px); align-items: center; }
.cta-kicker { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--accent, #e50914); margin-bottom: 0.7rem; }
.cta-title { font-size: clamp(1.6rem, 4vw, 3.2rem); font-weight: 900; line-height: 1.05; color: var(--text, #ffffff); margin-bottom: 1rem; letter-spacing: -0.03em; text-transform: uppercase; }
.cta-title em { font-style: normal; color: var(--accent, #e50914); }
.cta-sub { font-size: 0.88rem; color: var(--muted, #b3b3b3); line-height: 1.7; }
.cta-form label { display: block; font-size: 0.63rem; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted, #b3b3b3); margin-bottom: 8px; }
.cta-row { display: flex; border: 1px solid rgba(255,255,255,0.15); border-radius: 3px; overflow: hidden; transition: border-color var(--t); }
.cta-row:focus-within { border-color: var(--accent, #e50914); }
.cta-row input { flex: 1; min-width: 0; background: rgba(255,255,255,0.05); border: none; color: var(--text, #ffffff); font-family: var(--ff, sans-serif); font-size: 0.88rem; padding: 12px 14px; outline: none; }
.cta-row input::placeholder { color: var(--muted, #b3b3b3); }
.cta-row button { background: var(--accent, #e50914); border: none; color: #fff; font-family: var(--ff, sans-serif); font-size: 0.75rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; padding: 12px 18px; cursor: pointer; transition: background var(--t); white-space: nowrap; flex-shrink: 0; }
.cta-row button:hover { background: var(--accent-lt, #ff2b39); }
.cta-form small { display: block; font-size: 0.6rem; color: var(--muted, #b3b3b3); margin-top: 8px; }
.cta-thanks { font-size: 1.1rem; font-weight: 700; color: #4ade80; }

/* ── FOOTER — uses component palette ── */
.footer {
  background: var(--c-footer-bg);
  padding: clamp(36px, 6vw, 72px) 0 0;
  border-top: 1px solid var(--c-footer-border);
}
.footer-inner { display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr; gap: clamp(20px, 4vw, 56px); padding-bottom: clamp(28px, 5vw, 60px); }
.footer-logo { font-size: 1.4rem; font-weight: 900; color: var(--c-footer-accent); letter-spacing: -0.03em; margin-bottom: 10px; text-transform: uppercase; }
.footer-logo span { color: var(--c-footer-text); }
.footer-brand p { font-size: 0.82rem; color: var(--c-footer-muted); line-height: 1.65; margin-bottom: 16px; }
.socials { display: flex; gap: 8px; flex-wrap: wrap; }
.socials a, .social-link {
  font-size: 0.6rem; font-weight: 700; letter-spacing: 0.06em;
  color: var(--c-footer-muted); text-decoration: none;
  border: 1px solid var(--c-footer-border);
  padding: 6px 10px; border-radius: 3px;
  transition: border-color var(--t), color var(--t), background var(--t);
}
.socials a:hover, .social-link:hover {
  border-color: var(--c-footer-accent);
  color: var(--c-footer-accent);
  background: rgba(229,9,20,0.08);
}
.footer-nav { display: contents; }
.footer-col { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
.footer-col h4 { font-size: 0.6rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--c-footer-muted); margin-bottom: 4px; }
.footer-col a { font-size: 0.82rem; color: var(--c-footer-link); text-decoration: none; transition: color var(--t); }
.footer-col a:hover { color: var(--c-footer-text); }
.footer-bottom {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 0;
  border-top: 1px solid var(--c-footer-border);
  font-size: 0.63rem; letter-spacing: 0.06em;
  color: var(--c-footer-muted); gap: 8px;
}

/* ── YOUTUBE LITE EMBED ── */
.yt-lite {
  position: relative; overflow: hidden;
  background: #000; cursor: pointer;
  width: 100%; height: 100%;
}
.yt-bg-frame {
  position: absolute; top: 50%; left: 50%;
  width: 130%; height: 130%;
  transform: translate(-50%, -50%);
  pointer-events: none; border: none;
}
.yt-lite .yt-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0.15) 50%, rgba(0,0,0,0) 100%);
  transition: background 0.3s; pointer-events: none;
}
.yt-lite:hover .yt-overlay { background: rgba(0,0,0,0.35); }

/* ── YOUTUBE FEATURED ── */
.yt-featured { margin-bottom: 12px; }
.yt-featured > .yt-lite { aspect-ratio: 16 / 9; border-radius: 6px; }
.yt-featured-thumb {
  position: relative; width: 100%; aspect-ratio: 16/9;
  border-radius: 6px; overflow: hidden; cursor: pointer;
  background: #111; background-size: cover; background-position: center;
}
.yt-featured-thumb:hover { filter: brightness(0.9); }
.yt-featured-thumb .yt-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(to top, rgba(0,0,0,0.75) 0%, rgba(0,0,0,0.15) 50%, rgba(0,0,0,0) 100%);
  transition: background 0.3s;
}
.yt-featured-thumb:hover .yt-overlay { background: rgba(0,0,0,0.4); }

.yt-play-btn {
  position: absolute; top: 50%; left: 50%;
  transform: translate(-50%, -50%) scale(0.85);
  width: 72px; height: 72px; border-radius: 50%;
  background: var(--accent, #e50914); border: none; color: #fff;
  font-size: 1.4rem; cursor: pointer;
  display: flex; align-items: center; justify-content: center; padding-left: 5px;
  box-shadow: 0 6px 32px rgba(229,9,20,0.55);
  opacity: 0; transition: opacity 0.25s, transform 0.25s; z-index: 10;
}
.yt-lite:hover .yt-play-btn,
.yt-featured-thumb:hover .yt-play-btn { opacity: 1; transform: translate(-50%, -50%) scale(1); }

.yt-badge {
  position: absolute; top: 14px; left: 14px;
  background: var(--accent, #e50914); color: #fff;
  font-size: 0.58rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase;
  padding: 4px 10px; border-radius: 2px; z-index: 10; pointer-events: none;
}
.yt-featured-meta { display: flex; align-items: center; justify-content: space-between; padding: 10px 2px 0; gap: 8px; }
.yt-ch-link { font-size: 0.65rem; font-weight: 600; color: var(--muted, #b3b3b3); text-decoration: none; letter-spacing: 0.04em; transition: color 0.2s; }
.yt-ch-link:hover { color: var(--text, #ffffff); }

/* ── YOUTUBE GRID ── */
.yt-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-bottom: 20px; }
.yt-card { border-radius: 4px; overflow: hidden; background: var(--card, #1f1f1f); border: 1px solid var(--border); cursor: pointer; transition: transform 0.2s, border-color 0.2s; }
.yt-card:hover { transform: translateY(-3px); border-color: rgba(229,9,20,0.35); }
.yt-card > .yt-lite { aspect-ratio: 16 / 9; }
.yt-thumb {
  position: relative; aspect-ratio: 16/9; overflow: hidden;
  background: #111; background-size: cover; background-position: center;
}
.yt-card:hover .yt-thumb { background-size: 106%; }
.yt-thumb .yt-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.2); transition: background 0.3s; }
.yt-card:hover .yt-overlay { background: rgba(0,0,0,0.45); }

.yt-play-sm {
  position: absolute; top: 50%; left: 50%;
  transform: translate(-50%, -50%) scale(0.7);
  width: 36px; height: 36px; border-radius: 50%;
  background: var(--accent, #e50914); border: none; color: #fff;
  font-size: 0.75rem; cursor: pointer;
  display: flex; align-items: center; justify-content: center; padding-left: 2px;
  box-shadow: 0 4px 16px rgba(229,9,20,0.5);
  opacity: 0; transition: opacity 0.2s, transform 0.2s; z-index: 10;
}
.yt-lite:hover .yt-play-sm,
.yt-card:hover .yt-play-sm { opacity: 1; transform: translate(-50%, -50%) scale(1); }
.yt-card-meta { padding: 7px 9px; display: flex; align-items: center; gap: 6px; font-size: 0.6rem; color: var(--muted, #b3b3b3); }

/* ── YOUTUBE SUBSCRIBE ── */
.yt-sub-cta { display: flex; justify-content: center; padding-top: 4px; }
.btn-yt {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--accent, #e50914); color: #fff;
  font-family: var(--ff, sans-serif); font-size: 0.8rem; font-weight: 700;
  letter-spacing: 0.05em; text-transform: uppercase; text-decoration: none;
  padding: 11px 24px; border-radius: 3px; transition: background 0.2s, transform 0.2s;
}
.btn-yt:hover { background: var(--accent-lt, #ff2b39); transform: scale(1.02); }

/* Touch — always show play buttons */
@media (hover: none) {
  .yt-play-btn { opacity: 1 !important; transform: translate(-50%, -50%) scale(0.9) !important; }
  .yt-play-sm  { opacity: 1 !important; transform: translate(-50%, -50%) scale(0.9) !important; }
  .mc-btn { opacity: 1; transform: scale(0.9); }
}

/* ── ANIMATIONS ── */
@keyframes fade-up { from{ opacity:0; transform:translateY(14px); } to{ opacity:1; transform:translateY(0); } }

/* ══════════════════════════════
   RESPONSIVE
══════════════════════════════ */
@media (max-width: 1200px) {
  .music-grid { grid-template-columns: 1fr 1fr; }
  .mc-hero { grid-column: span 2; }
}

@media (max-width: 1024px) {
  .video-grid { grid-template-columns: 1fr 1fr; }
  .vc-main { grid-column: span 2; grid-row: span 1; }
  .yt-grid { grid-template-columns: repeat(3, 1fr); }
  .blog-grid { grid-template-columns: 1fr 1fr; }
  .bc-main { grid-row: span 1; }
  .footer-inner { grid-template-columns: 1fr 1fr; }
  .cta-inner { grid-template-columns: 1fr; gap: 28px; }
}

@media (max-width: 768px) {
  :root { --pad-x: 16px; --nav-h: 58px; }

  .nav-links {
    display: none; flex-direction: column; align-items: flex-start; gap: 0.75rem;
    position: absolute; top: var(--nav-h); left: 0; right: 0;
    background: var(--c-nav-mobile-bg);
    padding: 1.25rem var(--pad-x) 1.5rem;
    border-bottom: 1px solid var(--c-nav-border); z-index: 999;
  }
  .nav-links.open { display: flex; }
  .nav-links a { font-size: 0.95rem; padding: 4px 0; }
  .hamburger { display: flex; }

  .hero-vignette {
    background:
      linear-gradient(to bottom, var(--c-hero-vignette-t) 0%, rgba(0,0,0,0.15) 35%),
      linear-gradient(to top, var(--c-hero-vignette-b) 0%, rgba(0,0,0,0.0) 55%);
  }
  .hero-content { max-width: 100%; }
  .hero-bottom { flex-direction: column; align-items: flex-start; gap: 1rem; padding-bottom: 1.25rem; }
  .hero-scroll { display: none; }
  .hstat-div { display: none; }
  .hero-stats { gap: 1.5rem; }

  .music-grid { grid-template-columns: 1fr 1fr; }
  .mc-hero { grid-column: span 2; }

  .video-grid { grid-template-columns: 1fr; }
  .vc-main { grid-column: span 1; grid-row: span 1; }

  .yt-grid { grid-template-columns: repeat(2, 1fr); }
  .yt-play-btn { width: 52px; height: 52px; font-size: 1rem; opacity: 1; transform: translate(-50%, -50%) scale(0.9); }

  .blog-grid { grid-template-columns: 1fr; }
  .bc-main { grid-row: span 1; }

  .footer-inner { grid-template-columns: 1fr 1fr; }
  .footer-brand { grid-column: span 2; }

  .player-prog { display: none; }
  .player { padding: 10px 14px; gap: 0.75rem; }
}

@media (max-width: 540px) {
  .hero-actions { flex-direction: column; align-items: stretch; gap: 0.6rem; }
  .btn-primary, .btn-secondary { justify-content: center; text-align: center; }

  .music-grid { grid-template-columns: 1fr; }
  .mc-hero { grid-column: span 1; }
  .mc-hero .mc-art { height: 180px; }

  .cta-row { flex-direction: column; }
  .cta-row input { border-radius: 3px 3px 0 0; }
  .cta-row button { border-radius: 0 0 3px 3px; padding: 12px; text-align: center; }

  .footer-inner { grid-template-columns: 1fr; }
  .footer-brand { grid-column: span 1; }
  .footer-bottom { flex-direction: column; gap: 4px; text-align: center; }

  .sec-title-row { flex-direction: column; align-items: flex-start; gap: 0.4rem; }
}

@media (max-width: 480px) {
  .yt-grid { grid-template-columns: repeat(2, 1fr); gap: 6px; }
}

@media (max-width: 360px) {
  :root { --pad-x: 12px; }
  .hero-title { font-size: 1.9rem; }
  .hstat-n { font-size: 1.2rem; }
  .player-ctrl button:first-child,
  .player-ctrl button:last-child { display: none; }
}

/* ══════════════════════════════════════════════════════════
   LIGHT THEME OVERRIDES
   Requires: <script>document.documentElement.setAttribute(
   'data-theme','<?= $site["active_theme"] ?>');</script>
   in index.php right after <body>
══════════════════════════════════════════════════════════ */
html[data-theme="light"] {
  --border:  rgba(0,0,0,0.08);
  --border2: rgba(0,0,0,0.15);
  --dim:     rgba(0,0,0,0.04);
}
html[data-theme="light"] .page-body { box-shadow: 0 -2px 40px rgba(0,0,0,0.15); }
html[data-theme="light"] .bc-tags span {
  background: rgba(0,0,0,0.05);
  border-color: rgba(0,0,0,0.12);
}
html[data-theme="light"] .see-more { border-bottom-color: rgba(0,0,0,0.15); }
html[data-theme="light"] .see-more:hover { border-bottom-color: var(--text); }
html[data-theme="light"] .mc:hover { background: #f0f0f0; }
html[data-theme="light"] ::-webkit-scrollbar-track { background: var(--bg); }
html[data-theme="light"] .cta-row { border-color: rgba(0,0,0,0.15); }
html[data-theme="light"] .cta-row input {
  background: rgba(0,0,0,0.04);
  color: var(--text);
}
html[data-theme="light"] .cta-row input::placeholder { color: var(--muted); }
html[data-theme="light"] .btn-secondary {
  background: rgba(0,0,0,0.10);
  color: var(--text);
}
html[data-theme="light"] .btn-secondary:hover { background: rgba(0,0,0,0.18); }

/* NOTE: Nav, hero, player, footer, ticker are NOT overridden by light theme
   because they use --c-* component variables set independently in the admin. */
  </style>
<body>
<script>document.documentElement.setAttribute('data-theme','<?= htmlspecialchars($site['active_theme']) ?>');</script>
<!-- NAV -->
<nav class="nav" id="nav">
  <div class="nav-inner">
    <?= renderLogo($site, 'index.php', 'logo') ?>
    <ul class="nav-links" id="navLinks">
      <li><a href="index.php" class="is-active">Home</a></li>
      <li><a href="music.php">Music</a></li>
      <li><a href="video_blog.php">Videos</a></li>
      <li><a href="blog.php">Journal</a></li>
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
      <a href="music.php" class="btn-primary">
        <span class="btn-icon">▶</span> Start Listening
      </a>
      <a href="video_blog.php" class="btn-secondary">
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
          <a href="music.php" class="see-more">All tracks →</a>
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
          <a href="blog.php" class="see-more">All posts →</a>
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
          <form class="cta-form" id="subForm" method="POST" action="subscribe.php">
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
            <a href="index.php">Home</a>
            <a href="music.php">Music</a>
            <a href="video_blog.php">Videos</a>
            <a href="blog.php">Journal</a>
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