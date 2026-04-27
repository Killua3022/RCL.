<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/settings.php';

$d    = db();
$site = getAppearanceSettings($d);

// Top tracks
$topTracks = $d->query(
    "SELECT * FROM tracks WHERE is_published=1 ORDER BY plays DESC LIMIT 12"
)->fetchAll();

// New releases
$newTracks = $d->query(
    "SELECT * FROM tracks WHERE is_published=1 ORDER BY id DESC LIMIT 12"
)->fetchAll();

// All tracks
$allTracks = $d->query(
    "SELECT * FROM tracks WHERE is_published=1 ORDER BY sort_order ASC, id DESC"
)->fetchAll();

// Playlists (if table exists)
$playlists = [];
try {
    $playlists = $d->query(
        "SELECT * FROM playlists WHERE is_published=1 ORDER BY sort_order ASC, id DESC"
    )->fetchAll();
} catch (Exception $e) {}

function fmt($n) {
    if ($n >= 1000000) return round($n/1000000,1).'M';
    if ($n >= 1000)    return round($n/1000,1).'k';
    return $n;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Music — <?= h($site['logo_text']) ?></title>
<?= siteStyleTag($site) ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">

<style>
/* ══════════════════════════════════════════
   TOKENS & RESET
══════════════════════════════════════════ */
:root {
  --sp-bg:        #0a0a0f;
  --sp-surface:   #111118;
  --sp-card:      #16161f;
  --sp-card-h:    #1e1e2a;
  --sp-border:    rgba(255,255,255,0.06);
  --sp-green:     #e50914;
  --sp-green-dim: #e50914;
  --sp-accent:    #a855f7;
  --sp-accent2:   #ec4899;
  --sp-text:      #e8e8f0;
  --sp-muted:     #666680;
  --sp-dim:       #444458;
  --sp-sidebar:   200px;
  --sp-player:    80px;
  --sp-nav:       64px;
  --font-head:    'Syne', sans-serif;
  --font-body:    'DM Sans', sans-serif;
  --radius:       10px;
  --radius-lg:    16px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--font-body);
  background: var(--sp-bg);
  color: var(--sp-text);
  overflow-x: hidden;
}

/* ══════════════════════════════════════════
   LAYOUT SHELL
══════════════════════════════════════════ */
.sp-shell {
  display: grid;
  grid-template-columns: var(--sp-sidebar) 1fr;
  grid-template-rows: var(--sp-nav) 1fr var(--sp-player);
  grid-template-areas:
    "nav    nav"
    "side   main"
    "player player";
  height: 100dvh;
  overflow: hidden;
}

/* ══════════════════════════════════════════
   TOP NAV
══════════════════════════════════════════ */
.sp-nav {
  grid-area: nav;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  background: rgba(10,10,15,0.9);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--sp-border);
  z-index: 200;
  gap: 16px;
}
.sp-nav-logo {
  font-family: var(--font-head);
  font-weight: 800;
  font-size: 1.2rem;
  color: var(--sp-text);
  text-decoration: none;
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 8px;
}
.sp-nav-logo span {
  width: 28px; height: 28px; border-radius: 50%;
  background: var(--sp-green);
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; color: #000;
}
.sp-nav-links {
  display: flex; gap: 4px; list-style: none;
}
.sp-nav-links a {
  display: block;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: .85rem;
  font-weight: 500;
  color: var(--sp-muted);
  text-decoration: none;
  transition: color .2s, background .2s;
}
.sp-nav-links a:hover, .sp-nav-links a.active { color: var(--sp-text); background: var(--sp-surface); }
.sp-nav-links a.active { color: var(--sp-green); }
.sp-nav-search {
  flex: 1; max-width: 360px; position: relative;
}
.sp-nav-search input {
  width: 100%;
  background: var(--sp-surface);
  border: 1px solid var(--sp-border);
  border-radius: 24px;
  color: var(--sp-text);
  font-family: var(--font-body);
  font-size: .85rem;
  padding: 8px 16px 8px 38px;
  outline: none;
  transition: border-color .2s;
}
.sp-nav-search input:focus { border-color: var(--sp-green); }
.sp-nav-search svg {
  position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
  color: var(--sp-muted); pointer-events: none;
}

/* ══════════════════════════════════════════
   SIDEBAR
══════════════════════════════════════════ */
.sp-sidebar {
  grid-area: side;
  background: var(--sp-surface);
  border-right: 1px solid var(--sp-border);
  overflow-y: auto; overflow-x: hidden;
  padding: 16px 12px;
  scrollbar-width: thin;
  scrollbar-color: var(--sp-dim) transparent;
}
.sp-sidebar-section { margin-bottom: 24px; }
.sp-sidebar-label {
  font-size: .65rem; font-weight: 700; letter-spacing: .12em;
  text-transform: uppercase; color: var(--sp-muted);
  padding: 0 8px; margin-bottom: 8px;
}
.sp-sidebar-btn {
  display: flex; align-items: center; gap: 10px;
  width: 100%; padding: 9px 10px; border-radius: 8px;
  border: none; background: transparent;
  color: var(--sp-muted);
  font-family: var(--font-body); font-size: .82rem; font-weight: 500;
  text-align: left; cursor: pointer;
  transition: color .15s, background .15s;
  text-decoration: none;
}
.sp-sidebar-btn:hover, .sp-sidebar-btn.active { color: var(--sp-text); background: var(--sp-card); }
.sp-sidebar-btn.active { color: var(--sp-green); }
.sp-sidebar-btn svg { flex-shrink: 0; }
.sp-playlist-item {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 10px; border-radius: 8px; cursor: pointer;
  transition: background .15s;
}
.sp-playlist-item:hover { background: var(--sp-card); }
.sp-playlist-thumb {
  width: 38px; height: 38px; border-radius: 6px;
  background: var(--sp-card-h); flex-shrink: 0; overflow: hidden;
  display: flex; align-items: center; justify-content: center; font-size: 16px;
}
.sp-playlist-thumb img { width:100%; height:100%; object-fit:cover; }
.sp-playlist-name { font-size: .8rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--sp-text); }
.sp-playlist-meta { font-size: .7rem; color: var(--sp-muted); }

/* ══════════════════════════════════════════
   MAIN CONTENT
══════════════════════════════════════════ */
.sp-main {
  grid-area: main;
  overflow-y: auto; overflow-x: hidden;
  scrollbar-width: thin; scrollbar-color: var(--sp-dim) transparent;
}

/* ── Hero ── */
.sp-hero {
  position: relative; min-height: 340px;
  display: flex; align-items: flex-end;
  padding: 40px 32px; overflow: hidden;
}
.sp-hero-bg {
  position: absolute; inset: 0;
  background: linear-gradient(135deg, #0d1b3e 0%, #1a0533 40%, #0a0a0f 100%);
}
.sp-hero-bg::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(to bottom, transparent 40%, var(--sp-bg) 100%);
}
.sp-hero-particles { position: absolute; inset: 0; overflow: hidden; }
.sp-hero-particles::before, .sp-hero-particles::after {
  content: ''; position: absolute; border-radius: 50%; filter: blur(80px); opacity: .3;
}
.sp-hero-particles::before {
  width: 500px; height: 500px; background: var(--sp-accent);
  top: -100px; right: -100px; animation: floatA 8s ease-in-out infinite;
}
.sp-hero-particles::after {
  width: 300px; height: 300px; background: var(--sp-green);
  bottom: -50px; left: 20%; animation: floatB 10s ease-in-out infinite;
}
@keyframes floatA { 0%,100%{transform:translate(0,0)} 50%{transform:translate(-30px,20px)} }
@keyframes floatB { 0%,100%{transform:translate(0,0)} 50%{transform:translate(20px,-15px)} }
.sp-hero-content { position: relative; z-index: 1; }
.sp-hero-kicker {
  font-size: .7rem; font-weight: 700; letter-spacing: .15em; text-transform: uppercase;
  color: var(--sp-green); margin-bottom: 10px;
  display: flex; align-items: center; gap: 8px;
}
.sp-hero-kicker::before { content: ''; width: 24px; height: 2px; background: var(--sp-green); }
.sp-hero-title {
  font-family: var(--font-head); font-size: clamp(2rem, 5vw, 3.5rem);
  font-weight: 800; line-height: 1.05; margin-bottom: 14px;
}
.sp-hero-title em { color: var(--sp-green); font-style: normal; }
.sp-hero-sub { font-size: .9rem; color: var(--sp-muted); max-width: 400px; margin-bottom: 24px; }
.sp-hero-actions { display: flex; gap: 12px; flex-wrap: wrap; }
.btn-sp-green {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 24px; border-radius: 24px;
  background: var(--sp-green); color: #000;
  font-family: var(--font-body); font-weight: 700; font-size: .88rem;
  border: none; cursor: pointer; transition: transform .15s, background .15s;
  text-decoration: none;
}
.btn-sp-green:hover { background: #1ed760; transform: scale(1.03); }
.btn-sp-ghost {
  display: flex; align-items: center; gap: 8px;
  padding: 11px 22px; border-radius: 24px;
  background: transparent; border: 1px solid rgba(255,255,255,0.2);
  color: var(--sp-text);
  font-family: var(--font-body); font-weight: 500; font-size: .88rem;
  cursor: pointer; transition: border-color .15s, background .15s;
  text-decoration: none;
}
.btn-sp-ghost:hover { border-color: var(--sp-text); background: rgba(255,255,255,0.05); }

/* ── Section ── */
.sp-section { padding: 32px; }
.sp-section + .sp-section { padding-top: 0; }
.sp-section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.sp-section-title { font-family: var(--font-head); font-weight: 700; font-size: 1.25rem; }
.sp-section-all { font-size: .8rem; font-weight: 600; color: var(--sp-muted); text-decoration: none; transition: color .15s; }
.sp-section-all:hover { color: var(--sp-text); }

/* ── Horizontal row ── */
.sp-row { display: flex; gap: 14px; overflow-x: auto; padding-bottom: 8px; scrollbar-width: none; }
.sp-row::-webkit-scrollbar { display: none; }

/* ── Card ── */
.sp-card {
  flex: 0 0 160px; background: var(--sp-card);
  border-radius: var(--radius-lg); overflow: hidden;
  cursor: pointer; transition: transform .22s, background .22s; position: relative;
}
.sp-card:hover { transform: translateY(-6px) scale(1.03); background: var(--sp-card-h); }
.sp-card-art { position: relative; aspect-ratio: 1; overflow: hidden; background: var(--sp-card-h); }
.sp-card-art img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s; }
.sp-card:hover .sp-card-art img { transform: scale(1.07); }
.sp-card-art-placeholder {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, #1a0533, #0d1b3e);
  color: rgba(255,255,255,0.15); font-size: .7rem; font-weight: 700;
  letter-spacing: .1em; text-transform: uppercase;
}
.sp-card-preview {
  position: absolute; inset: 0; background: rgba(0,0,0,.5);
  display: flex; align-items: center; justify-content: center;
  opacity: 0; transition: opacity .2s;
}
.sp-card:hover .sp-card-preview { opacity: 1; }
.sp-card-play {
  width: 44px; height: 44px; border-radius: 50%;
  background: var(--sp-green); border: none; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  color: #000; font-size: 16px;
  transform: scale(.8) translateY(6px); transition: transform .2s;
  box-shadow: 0 4px 20px rgba(234, 44, 11, 0.4);
}
.sp-card:hover .sp-card-play { transform: scale(1) translateY(0); }
.sp-card-lyrics-btn {
  position: absolute; top: 8px; right: 8px;
  background: rgba(0,0,0,.6); border: none; border-radius: 6px;
  color: var(--sp-text); font-size: 10px; padding: 4px 7px;
  cursor: pointer; opacity: 0; transition: opacity .2s;
  font-family: var(--font-body);
}
.sp-card:hover .sp-card-lyrics-btn { opacity: 1; }
.sp-card-body { padding: 12px; }
.sp-card-title { font-size: .85rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 4px; }
.sp-card-meta { font-size: .72rem; color: var(--sp-muted); display: flex; align-items: center; justify-content: space-between; }
.sp-card-plays { display: flex; align-items: center; gap: 4px; }
.sp-card-badge {
  position: absolute; top: 8px; left: 8px;
  background: var(--sp-green); color: #000;
  font-size: .6rem; font-weight: 700;
  padding: 3px 7px; border-radius: 4px;
  text-transform: uppercase; letter-spacing: .05em;
}

/* ── Wide Card ── */
.sp-card-wide {
  flex: 0 0 320px; background: var(--sp-card);
  border-radius: var(--radius-lg); overflow: hidden;
  cursor: pointer; position: relative; transition: transform .22s;
}
.sp-card-wide:hover { transform: translateY(-4px); }
.sp-card-wide-art { height: 180px; position: relative; overflow: hidden; }
.sp-card-wide-art img { width:100%; height:100%; object-fit:cover; transition: transform .3s; }
.sp-card-wide:hover .sp-card-wide-art img { transform: scale(1.05); }
.sp-card-wide-art-placeholder {
  position: absolute; inset: 0;
  background: linear-gradient(135deg, #1a0533, #0d1b3e);
  display: flex; align-items: center; justify-content: center;
  color: rgba(255,255,255,0.15); font-size: .7rem; font-weight: 700;
  letter-spacing: .1em; text-transform: uppercase;
}
.sp-card-wide-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(22,22,31,.9), transparent 50%); }
.sp-card-wide-body { padding: 14px; }
.sp-card-wide-title { font-size: 1rem; font-weight: 700; margin-bottom: 4px; }
.sp-card-wide-sub { font-size: .78rem; color: var(--sp-muted); }
.sp-card-wide-play {
  position: absolute; bottom: 70px; right: 16px;
  width: 44px; height: 44px; border-radius: 50%;
  background: var(--sp-green); border: none;
  display: flex; align-items: center; justify-content: center;
  color: #000; font-size: 16px; cursor: pointer;
  opacity: 0; transform: translateY(8px);
  transition: opacity .2s, transform .2s;
  box-shadow: 0 4px 20px rgba(234, 44, 11, 0.4);
}
.sp-card-wide:hover .sp-card-wide-play { opacity: 1; transform: translateY(0); }

/* ── Track List ── */
.sp-tracklist { width: 100%; border-collapse: collapse; }
.sp-tracklist thead th {
  text-align: left; font-size: .7rem; font-weight: 600;
  letter-spacing: .08em; text-transform: uppercase; color: var(--sp-muted);
  padding: 8px 12px; border-bottom: 1px solid var(--sp-border);
}
.sp-tracklist tbody tr { border-radius: 8px; transition: background .15s; cursor: pointer; }
.sp-tracklist tbody tr:hover { background: var(--sp-card); }
.sp-tracklist tbody tr.playing { background: rgba(29,185,84,.08); }
.sp-tracklist tbody tr.playing .tl-title { color: var(--sp-green); }
.sp-tracklist td { padding: 10px 12px; vertical-align: middle; }
.tl-num { font-size: .85rem; color: var(--sp-muted); width: 36px; text-align: center; }
.tl-art { display: flex; align-items: center; gap: 12px; }
.tl-thumb {
  width: 40px; height: 40px; border-radius: 6px;
  background: var(--sp-card-h); overflow: hidden; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.tl-thumb img { width:100%; height:100%; object-fit:cover; }
.tl-thumb-placeholder {
  width: 100%; height: 100%;
  background: linear-gradient(135deg, #1a0533, #0d1b3e);
  display: flex; align-items: center; justify-content: center;
  color: rgba(255,255,255,0.2); font-size: 8px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .05em;
}
.tl-title { font-size: .88rem; font-weight: 500; }
.tl-artist { font-size: .75rem; color: var(--sp-muted); margin-top: 2px; }
.tl-type { font-size: .75rem; color: var(--sp-muted); }
.tl-plays { font-size: .82rem; color: var(--sp-muted); }
.tl-dur { font-size: .82rem; color: var(--sp-muted); }
.tl-actions { display: flex; align-items: center; gap: 8px; }
.tl-like, .tl-lyrics-open { background: none; border: none; cursor: pointer; font-size: 14px; color: var(--sp-dim); transition: color .15s; padding: 4px; }
.tl-like:hover { color: var(--sp-accent2); }
.tl-lyrics-open:hover { color: var(--sp-accent); }
.tl-like.liked { color: var(--sp-accent2); }

/* ══════════════════════════════════════════
   LYRICS PANEL
══════════════════════════════════════════ */
.sp-lyrics-panel {
  position: fixed; top: 0; right: 0;
  width: 380px; height: 100dvh;
  background: var(--sp-surface); border-left: 1px solid var(--sp-border);
  z-index: 500; display: flex; flex-direction: column;
  transform: translateX(100%);
  transition: transform .3s cubic-bezier(.4,0,.2,1);
}
.sp-lyrics-panel.open { transform: translateX(0); }
.sp-lyrics-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 20px 24px; border-bottom: 1px solid var(--sp-border);
}
.sp-lyrics-header h3 { font-family: var(--font-head); font-weight: 700; font-size: 1rem; }
.sp-lyrics-close { background: none; border: none; color: var(--sp-muted); cursor: pointer; font-size: 18px; transition: color .15s; }
.sp-lyrics-close:hover { color: var(--sp-text); }
.sp-lyrics-track { display: flex; align-items: center; gap: 12px; padding: 16px 24px; border-bottom: 1px solid var(--sp-border); }
.sp-lyrics-thumb { width: 48px; height: 48px; border-radius: 8px; background: var(--sp-card-h); overflow: hidden; display: flex; align-items: center; justify-content: center; }
.sp-lyrics-thumb img { width:100%; height:100%; object-fit:cover; }
.sp-lyrics-track-title { font-weight: 600; font-size: .9rem; }
.sp-lyrics-track-artist { font-size: .78rem; color: var(--sp-muted); margin-top: 2px; }
.sp-lyrics-body { flex: 1; overflow-y: auto; padding: 24px; scrollbar-width: thin; scrollbar-color: var(--sp-dim) transparent; }
.sp-lyrics-text { font-family: var(--font-head); font-size: 1.15rem; font-weight: 600; line-height: 2; color: var(--sp-muted); }
.sp-lyrics-text .ly-line.active { color: var(--sp-text); font-size: 1.3rem; }
.sp-lyrics-text .ly-line { display: block; margin-bottom: 4px; transition: color .3s, font-size .3s; cursor: default; }
.sp-lyrics-text .ly-line:hover { color: var(--sp-text); }
.sp-lyrics-empty { color: var(--sp-muted); font-size: .88rem; text-align: center; margin-top: 60px; line-height: 1.8; }
.sp-lyrics-empty span { font-size: 2rem; display: block; margin-bottom: 12px; }

/* ══════════════════════════════════════════
   MINI PLAYER
══════════════════════════════════════════ */
.sp-player {
  grid-area: player;
  display: grid; grid-template-columns: 1fr 1fr 1fr;
  align-items: center; padding: 0 24px;
  background: var(--sp-surface); border-top: 1px solid var(--sp-border);
  gap: 20px;
}
.sp-player-left { display: flex; align-items: center; gap: 12px; min-width: 0; }

/* ── Player thumbnail — always shows image, never emoji ── */
.sp-player-thumb {
  width: 48px; height: 48px; border-radius: 8px;
  background: var(--sp-card-h); flex-shrink: 0; overflow: hidden;
  position: relative;
}
.sp-player-thumb img {
  width: 100%; height: 100%; object-fit: cover; border-radius: 8px;
  display: block;
}
.sp-player-thumb .thumb-placeholder {
  width: 100%; height: 100%;
  background: linear-gradient(135deg, #1a0533, #0d1b3e);
  display: flex; align-items: center; justify-content: center;
  color: rgba(255,255,255,0.25); font-size: 9px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .06em;
}
/* Spinning ring overlay when playing */
.sp-player-thumb::after {
  content: '';
  position: absolute; inset: -2px;
  border-radius: 10px;
  border: 2px solid transparent;
  transition: border-color .3s;
}
.sp-player.playing .sp-player-thumb::after {
  border-color: var(--sp-green);
  animation: thumbSpin 3s linear infinite;
}
@keyframes thumbSpin {
  0%   { box-shadow: 0 0 0 0 rgba(29,185,84,0); }
  50%  { box-shadow: 0 0 12px 2px rgba(234, 44, 11, 0.4); }
  100% { box-shadow: 0 0 0 0 rgba(29,185,84,0); }
}

.sp-player-info { min-width: 0; }
.sp-player-title { font-size: .88rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sp-player-artist { font-size: .75rem; color: var(--sp-muted); }
.sp-player-heart { background: none; border: none; cursor: pointer; font-size: 14px; color: var(--sp-dim); margin-left: 8px; flex-shrink: 0; transition: color .15s; }
.sp-player-heart.liked { color: var(--sp-accent2); }
.sp-player-center { display: flex; flex-direction: column; align-items: center; gap: 8px; }
.sp-player-btns { display: flex; align-items: center; gap: 12px; }
.sp-player-btn { background: none; border: none; cursor: pointer; color: var(--sp-muted); font-size: 16px; transition: color .15s; padding: 4px; }
.sp-player-btn:hover { color: var(--sp-text); }
.sp-player-main-btn {
  width: 36px; height: 36px; border-radius: 50%;
  background: var(--sp-text); color: var(--sp-bg);
  border: none; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  font-size: 15px; transition: transform .15s, background .15s;
}
.sp-player-main-btn:hover { transform: scale(1.07); background: #fff; }
.sp-player-prog { display: flex; align-items: center; gap: 10px; width: 100%; max-width: 400px; }
.sp-player-time { font-size: .7rem; color: var(--sp-muted); min-width: 32px; }
.sp-player-time:last-child { text-align: right; }
.sp-player-bar { flex: 1; height: 4px; border-radius: 2px; background: var(--sp-dim); cursor: pointer; position: relative; }
.sp-player-bar:hover { height: 6px; }
.sp-player-fill { height: 100%; border-radius: 2px; background: var(--sp-text); transition: width .5s linear; }
.sp-player-bar:hover .sp-player-fill { background: var(--sp-green); }
.sp-player-right { display: flex; align-items: center; justify-content: flex-end; gap: 12px; }
.sp-vol { display: flex; align-items: center; gap: 8px; }
.sp-vol-bar { width: 80px; height: 4px; border-radius: 2px; background: var(--sp-dim); cursor: pointer; position: relative; }
.sp-vol-fill { height: 100%; border-radius: 2px; background: var(--sp-text); width: 70%; }
.sp-lyrics-toggle {
  background: none; border: none; cursor: pointer;
  font-size: 12px; font-weight: 600; letter-spacing: .06em;
  color: var(--sp-muted); font-family: var(--font-body);
  padding: 5px 10px; border-radius: 6px;
  transition: color .15s, background .15s;
}
.sp-lyrics-toggle:hover, .sp-lyrics-toggle.active { color: var(--sp-text); background: var(--sp-card); }
.sp-lyrics-toggle.active { color: var(--sp-green); }

/* ══════════════════════════════════════════
   TABS
══════════════════════════════════════════ */
.sp-tabs { display: flex; gap: 8px; padding: 24px 32px 0; }
.sp-tab {
  padding: 8px 18px; border-radius: 20px;
  background: var(--sp-card); border: none;
  color: var(--sp-muted); font-family: var(--font-body);
  font-size: .82rem; font-weight: 600; cursor: pointer;
  transition: background .15s, color .15s;
}
.sp-tab:hover { background: var(--sp-card-h); color: var(--sp-text); }
.sp-tab.active { background: var(--sp-text); color: var(--sp-bg); }

/* scrollbars */
.sp-main::-webkit-scrollbar, .sp-sidebar::-webkit-scrollbar, .sp-lyrics-body::-webkit-scrollbar { width: 4px; }
.sp-main::-webkit-scrollbar-track, .sp-sidebar::-webkit-scrollbar-track { background: transparent; }
.sp-main::-webkit-scrollbar-thumb { background: var(--sp-dim); border-radius: 2px; }

/* ══════════════════════════════════════════
   RESPONSIVE
══════════════════════════════════════════ */
@media (max-width: 900px) {
  .sp-shell { grid-template-columns: 1fr; grid-template-areas: "nav" "main" "player"; }
  .sp-sidebar { display: none; }
  .sp-player { grid-template-columns: 1fr auto; }
  .sp-player-right { display: none; }
  .sp-lyrics-panel { width: 100%; }
}
@media (max-width: 600px) {
  .sp-section { padding: 20px 16px; }
  .sp-tabs { padding: 16px 16px 0; }
  .sp-card { flex: 0 0 140px; }
}
</style>
</head>

<body>
<div class="sp-shell" id="spShell">

<!-- ══ TOP NAV ══ -->
<nav class="sp-nav">
  <a href="index.php" class="sp-nav-logo">
    <span>♪</span> <?= h($site['logo_text']) ?>
  </a>
  <div class="sp-nav-search">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
    </svg>
    <input type="text" id="searchInput" placeholder="Search songs, artists…">
  </div>
  <ul class="sp-nav-links">
    <li><a href="index.php">Home</a></li>
    <li><a href="music.php" class="active">Music</a></li>
    <li><a href="video_blog.php">Videos</a></li>
    <li><a href="blog.php">Journal</a></li>
  </ul>
</nav>

<!-- ══ SIDEBAR ══ -->
<aside class="sp-sidebar">
  <div class="sp-sidebar-section">
    <div class="sp-sidebar-label">Browse</div>
    <button class="sp-sidebar-btn active" onclick="showSection('featured',this)">
      <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
      Discover
    </button>
    <button class="sp-sidebar-btn" onclick="showSection('top',this)">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
      Top Tracks
    </button>
    <button class="sp-sidebar-btn" onclick="showSection('new',this)">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
      New Releases
    </button>
    <button class="sp-sidebar-btn" onclick="showSection('all',this)">
      <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
      All Songs
    </button>
  </div>
  <div class="sp-sidebar-section">
    <div class="sp-sidebar-label">Library</div>
    <button class="sp-sidebar-btn" onclick="showSection('liked',this)">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
      Liked Songs
    </button>
  </div>
  <?php if ($playlists): ?>
  <div class="sp-sidebar-section">
    <div class="sp-sidebar-label">Playlists</div>
    <?php foreach ($playlists as $pl): ?>
    <div class="sp-playlist-item" onclick="loadPlaylist(<?= (int)$pl['id'] ?>)">
      <div class="sp-playlist-thumb">
        <?php if (!empty($pl['cover_image'])): ?>
        <img src="/<?= h($pl['cover_image']) ?>" alt="">
        <?php else: ?>
        <div style="width:100%;height:100%;background:linear-gradient(135deg,#1a0533,#0d1b3e)"></div>
        <?php endif; ?>
      </div>
      <div class="sp-playlist-info">
        <div class="sp-playlist-name"><?= h($pl['name'] ?? $pl['title'] ?? 'Playlist') ?></div>
        <div class="sp-playlist-meta">Playlist</div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</aside>

<!-- ══ MAIN CONTENT ══ -->
<main class="sp-main" id="spMain">

  <!-- HERO -->
  <div class="sp-hero">
    <div class="sp-hero-bg"></div>
    <div class="sp-hero-particles"></div>
    <div class="sp-hero-content">
      <div class="sp-hero-kicker">Now Streaming</div>
      <h1 class="sp-hero-title"><?= h($site['logo_text']) ?><br><em>Music</em></h1>
      <p class="sp-hero-sub"><?= count($allTracks) ?>+ tracks · Top hits · New releases</p>
      <div class="sp-hero-actions">
        <button class="btn-sp-green" onclick="shuffleAll()">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/>
            <polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/>
          </svg>
          Shuffle All
        </button>
        <button class="btn-sp-ghost" onclick="playAll()">▶ Play All</button>
      </div>
    </div>
  </div>

  <!-- TABS -->
  <div class="sp-tabs">
    <button class="sp-tab active" onclick="switchTab('featured',this)">Featured</button>
    <button class="sp-tab" onclick="switchTab('top',this)">Hot Right Now</button>
    <button class="sp-tab" onclick="switchTab('new',this)">New</button>
    <button class="sp-tab" onclick="switchTab('all',this)">All Songs</button>
  </div>

  <!-- FEATURED -->
  <section class="sp-section" id="sec-featured">
    <div class="sp-section-head">
      <h2 class="sp-section-title">Featured</h2>
      <a href="#" class="sp-section-all" onclick="switchTab('all',document.querySelectorAll('.sp-tab')[3]);return false">Show all</a>
    </div>
    <div class="sp-row">
      <?php foreach (array_slice($topTracks, 0, 8) as $i => $t):
        $src   = !empty($t['file_path']) ? '/' . $t['file_path'] : (!empty($t['youtube_id']) ? 'yt:' . $t['youtube_id'] : '');
        $cover = !empty($t['cover_image']) ? '/' . h($t['cover_image']) : '';
        $ytThumb = !empty($t['youtube_id']) ? 'https://img.youtube.com/vi/' . h($t['youtube_id']) . '/mqdefault.jpg' : '';
        $artImg  = $cover ?: $ytThumb;
      ?>
      <div class="sp-card-wide"
           data-audio="<?= h($src) ?>"
           data-title="<?= h($t['title']) ?>"
           data-artist="<?= h($t['artist']) ?>"
           data-id="<?= (int)$t['id'] ?>"
           data-lyrics="<?= h($t['lyrics'] ?? '') ?>"
           data-cover="<?= h($artImg) ?>"
           onclick="playCardWide(this)">
        <div class="sp-card-wide-art">
          <?php if ($artImg): ?>
          <img src="<?= h($artImg) ?>" alt="<?= h($t['title']) ?>">
          <?php else: ?>
          <div class="sp-card-wide-art-placeholder">No Art</div>
          <?php endif; ?>
          <div class="sp-card-wide-overlay"></div>
        </div>
        <div class="sp-card-wide-body">
          <div class="sp-card-wide-title"><?= h($t['title']) ?></div>
          <div class="sp-card-wide-sub"><?= h($t['artist']) ?> · <?= fmt($t['plays'] ?? 0) ?> plays</div>
        </div>
        <button class="sp-card-wide-play" onclick="event.stopPropagation();playCardWide(this.closest('.sp-card-wide'))">▶</button>
        <?php if ($i === 0): ?><div class="sp-card-badge">Top</div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- TOP TRACKS -->
  <section class="sp-section" id="sec-top" style="display:none">
    <div class="sp-section-head">
      <h2 class="sp-section-title">Hot Right Now</h2>
    </div>
    <div class="sp-row">
      <?php foreach ($topTracks as $i => $t):
        $src     = !empty($t['file_path']) ? '/' . $t['file_path'] : (!empty($t['youtube_id']) ? 'yt:' . $t['youtube_id'] : '');
        $cover   = !empty($t['cover_image']) ? '/' . h($t['cover_image']) : '';
        $ytThumb = !empty($t['youtube_id']) ? 'https://img.youtube.com/vi/' . h($t['youtube_id']) . '/mqdefault.jpg' : '';
        $artImg  = $cover ?: $ytThumb;
      ?>
      <div class="sp-card"
           data-audio="<?= h($src) ?>"
           data-title="<?= h($t['title']) ?>"
           data-artist="<?= h($t['artist']) ?>"
           data-id="<?= (int)$t['id'] ?>"
           data-lyrics="<?= h($t['lyrics'] ?? '') ?>"
           data-cover="<?= h($artImg) ?>">
        <div class="sp-card-art">
          <?php if ($artImg): ?>
          <img src="<?= h($artImg) ?>" alt="">
          <?php else: ?>
          <div class="sp-card-art-placeholder">No Art</div>
          <?php endif; ?>
          <div class="sp-card-preview">
            <button class="sp-card-play" onclick="playCard(this.closest('.sp-card'))">▶</button>
          </div>
          <button class="sp-card-lyrics-btn" onclick="event.stopPropagation();openLyrics(this.closest('.sp-card'))">Lyrics</button>
          <?php if ($i < 3): ?><div class="sp-card-badge">#<?= $i+1 ?></div><?php endif; ?>
        </div>
        <div class="sp-card-body">
          <div class="sp-card-title"><?= h($t['title']) ?></div>
          <div class="sp-card-meta">
            <span><?= h($t['artist']) ?></span>
            <span class="sp-card-plays">▶ <?= fmt($t['plays'] ?? 0) ?></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- NEW RELEASES -->
  <section class="sp-section" id="sec-new" style="display:none">
    <div class="sp-section-head">
      <h2 class="sp-section-title">New Releases</h2>
    </div>
    <div class="sp-row">
      <?php foreach ($newTracks as $i => $t):
        $src     = !empty($t['file_path']) ? '/' . $t['file_path'] : (!empty($t['youtube_id']) ? 'yt:' . $t['youtube_id'] : '');
        $cover   = !empty($t['cover_image']) ? '/' . h($t['cover_image']) : '';
        $ytThumb = !empty($t['youtube_id']) ? 'https://img.youtube.com/vi/' . h($t['youtube_id']) . '/mqdefault.jpg' : '';
        $artImg  = $cover ?: $ytThumb;
      ?>
      <div class="sp-card"
           data-audio="<?= h($src) ?>"
           data-title="<?= h($t['title']) ?>"
           data-artist="<?= h($t['artist']) ?>"
           data-id="<?= (int)$t['id'] ?>"
           data-lyrics="<?= h($t['lyrics'] ?? '') ?>"
           data-cover="<?= h($artImg) ?>">
        <div class="sp-card-art">
          <?php if ($artImg): ?>
          <img src="<?= h($artImg) ?>" alt="">
          <?php else: ?>
          <div class="sp-card-art-placeholder">No Art</div>
          <?php endif; ?>
          <div class="sp-card-preview">
            <button class="sp-card-play" onclick="playCard(this.closest('.sp-card'))">▶</button>
          </div>
          <button class="sp-card-lyrics-btn" onclick="event.stopPropagation();openLyrics(this.closest('.sp-card'))">Lyrics</button>
          <div class="sp-card-badge">New</div>
        </div>
        <div class="sp-card-body">
          <div class="sp-card-title"><?= h($t['title']) ?></div>
          <div class="sp-card-meta"><span><?= h($t['artist']) ?></span></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ALL SONGS TABLE -->
  <section class="sp-section" id="sec-all" style="display:none">
    <div class="sp-section-head">
      <h2 class="sp-section-title">All Songs</h2>
      <span style="font-size:.8rem;color:var(--sp-muted)"><?= count($allTracks) ?> tracks</span>
    </div>
    <table class="sp-tracklist" id="trackTable">
      <thead>
        <tr>
          <th>#</th><th>Title</th><th>Type</th><th>Plays</th><th>Duration</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($allTracks as $i => $t):
          $src     = !empty($t['file_path']) ? '/' . $t['file_path'] : (!empty($t['youtube_id']) ? 'yt:' . $t['youtube_id'] : '');
          $cover   = !empty($t['cover_image']) ? '/' . h($t['cover_image']) : '';
          $ytThumb = !empty($t['youtube_id']) ? 'https://img.youtube.com/vi/' . h($t['youtube_id']) . '/mqdefault.jpg' : '';
          $artImg  = $cover ?: $ytThumb;
        ?>
        <tr class="track-row"
            data-audio="<?= h($src) ?>"
            data-title="<?= h($t['title']) ?>"
            data-artist="<?= h($t['artist']) ?>"
            data-id="<?= (int)$t['id'] ?>"
            data-lyrics="<?= h($t['lyrics'] ?? '') ?>"
            data-cover="<?= h($artImg) ?>"
            onclick="playRow(this)">
          <td class="tl-num"><?= $i + 1 ?></td>
          <td>
            <div class="tl-art">
              <div class="tl-thumb">
                <?php if ($artImg): ?>
                <img src="<?= h($artImg) ?>" alt="">
                <?php else: ?>
                <div class="tl-thumb-placeholder">No Art</div>
                <?php endif; ?>
              </div>
              <div>
                <div class="tl-title"><?= h($t['title']) ?></div>
                <div class="tl-artist"><?= h($t['artist']) ?></div>
              </div>
            </div>
          </td>
          <td class="tl-type"><?= ucfirst(h($t['type'] ?? 'original')) ?></td>
          <td class="tl-plays"><?= fmt($t['plays'] ?? 0) ?></td>
          <td class="tl-dur"><?= h($t['duration'] ?? '—') ?></td>
          <td class="tl-actions" onclick="event.stopPropagation()">
            <button class="tl-like" onclick="toggleLike(this,<?= (int)$t['id'] ?>)" title="Like">♡</button>
            <?php if (!empty($t['lyrics'])): ?>
            <button class="tl-lyrics-open" onclick="openLyricsRow(this.closest('tr'))" title="Lyrics">✦</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <!-- LIKED -->
  <section class="sp-section" id="sec-liked" style="display:none">
    <div class="sp-section-head">
      <h2 class="sp-section-title">♥ Liked Songs</h2>
    </div>
    <div id="liked-container">
      <p style="color:var(--sp-muted);padding:40px 0;text-align:center">Like songs to see them here ♡</p>
    </div>
  </section>

</main>

<!-- ══ LYRICS PANEL ══ -->
<aside class="sp-lyrics-panel" id="lyricsPanel">
  <div class="sp-lyrics-header">
    <h3>Lyrics</h3>
    <button class="sp-lyrics-close" onclick="closeLyrics()">✕</button>
  </div>
  <div class="sp-lyrics-track" id="lyricsMeta">
    <div class="sp-lyrics-thumb" id="lyricsThumb">
      <div style="width:100%;height:100%;background:linear-gradient(135deg,#1a0533,#0d1b3e)"></div>
    </div>
    <div>
      <div class="sp-lyrics-track-title" id="lyricsTitle">—</div>
      <div class="sp-lyrics-track-artist" id="lyricsArtist">—</div>
    </div>
  </div>
  <div class="sp-lyrics-body">
    <div class="sp-lyrics-text" id="lyricsText">
      <div class="sp-lyrics-empty">
        <span>🎤</span>Select a track to see lyrics
      </div>
    </div>
  </div>
</aside>

<!-- ══ MINI PLAYER ══ -->
<div class="sp-player" id="spPlayer">
  <div class="sp-player-left">
    <div class="sp-player-thumb" id="playerThumb">
      <div class="thumb-placeholder">No Track</div>
    </div>
    <div class="sp-player-info">
      <div class="sp-player-title" id="playerTitle">Select a track</div>
      <div class="sp-player-artist" id="playerArtist">—</div>
    </div>
    <button class="sp-player-heart" id="playerHeart" onclick="togglePlayerLike()">♡</button>
  </div>

  <div class="sp-player-center">
    <div class="sp-player-btns">
      <button class="sp-player-btn" onclick="prevTrack()" title="Previous">⏮</button>
      <button class="sp-player-main-btn" id="playBtn" onclick="togglePlay()">▶</button>
      <button class="sp-player-btn" onclick="nextTrack()" title="Next">⏭</button>
    </div>
    <div class="sp-player-prog">
      <span class="sp-player-time" id="currentTime">0:00</span>
      <div class="sp-player-bar" id="progressBar" onclick="seekTo(event)">
        <div class="sp-player-fill" id="progressFill"></div>
      </div>
      <span class="sp-player-time" id="totalTime">0:00</span>
    </div>
  </div>

  <div class="sp-player-right">
    <button class="sp-lyrics-toggle" id="lyricsToggleBtn" onclick="toggleLyricsPanel()">Lyrics</button>
    <div class="sp-vol">
      <span style="font-size:14px;color:var(--sp-muted)">🔊</span>
      <div class="sp-vol-bar" onclick="setVolume(event)">
        <div class="sp-vol-fill" id="volFill" style="width:70%"></div>
      </div>
    </div>
  </div>
</div>

<!-- Hidden YouTube container -->
<div style="position:fixed;width:0;height:0;overflow:hidden;opacity:0;pointer-events:none">
  <div id="yt-player"></div>
</div>

</div><!-- /sp-shell -->

<script>
// ══════════════════════════════
//  TRACK DATA — includes ytid for thumbnail fallback
// ══════════════════════════════
const allTracks = <?= json_encode(array_map(function($t) {
  $src = '';
  if (!empty($t['file_path']))      $src = '/' . $t['file_path'];
  elseif (!empty($t['youtube_id'])) $src = 'yt:' . $t['youtube_id'];
  $cover = !empty($t['cover_image']) ? '/' . $t['cover_image'] : '';
  // If no custom cover, use YouTube thumbnail
  if (!$cover && !empty($t['youtube_id'])) {
    $cover = 'https://img.youtube.com/vi/' . $t['youtube_id'] . '/mqdefault.jpg';
  }
  return [
    'id'     => (int)$t['id'],
    'title'  => $t['title'],
    'artist' => $t['artist'],
    'src'    => $src,
    'cover'  => $cover,   // always populated when any art source available
    'ytid'   => $t['youtube_id'] ?? '',
    'lyrics' => $t['lyrics'] ?? '',
    'dur'    => $t['duration'] ?? '',
    'plays'  => (int)($t['plays'] ?? 0),
  ];
}, $allTracks), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

// ══════════════════════════════
//  STATE
// ══════════════════════════════
let trackIdx     = 0;
let playing      = false;
let audio        = null;
let ytPlayer     = null, ytReady = false, ytPending = null;
let currentType  = null;
let ytTimer      = null;
let likedIds     = new Set(JSON.parse(localStorage.getItem('liked') || '[]'));
let queue        = [...allTracks];
let lyricsOpen   = false;
let currentTrack = null;

// ══════════════════════════════
//  TAB / SECTION SWITCHING
// ══════════════════════════════
function switchTab(id, btn) {
  document.querySelectorAll('.sp-section[id^="sec-"]').forEach(s => s.style.display = 'none');
  const sec = document.getElementById('sec-' + id);
  if (sec) sec.style.display = '';
  document.querySelectorAll('.sp-tab').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
}

function showSection(id, btn) {
  const map = {featured:'featured', top:'top', new:'new', all:'all', liked:'liked'};
  switchTab(map[id] || 'featured', null);
  document.getElementById('spMain').scrollTop = 0;
  document.querySelectorAll('.sp-sidebar-btn').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
}

// ══════════════════════════════
//  PLAYER THUMBNAIL HELPER
//  Resolves the best image for the thumb — never emoji
// ══════════════════════════════
function setPlayerThumb(cover, ytid) {
  const el = document.getElementById('playerThumb');
  // Priority: 1) uploaded cover  2) YouTube thumbnail  3) styled placeholder
  const imgSrc = cover || (ytid ? 'https://img.youtube.com/vi/' + ytid + '/mqdefault.jpg' : '');
  if (imgSrc) {
    el.innerHTML = '<img src="' + imgSrc + '" alt="">';
  } else {
    el.innerHTML = '<div class="thumb-placeholder">No Art</div>';
  }
}

// ══════════════════════════════
//  PLAY FUNCTIONS
// ══════════════════════════════
function getTrackData(el) {
  return {
    src    : el.dataset.audio  || '',
    title  : el.dataset.title  || '—',
    artist : el.dataset.artist || '—',
    id     : parseInt(el.dataset.id) || 0,
    lyrics : el.dataset.lyrics || '',
    cover  : el.dataset.cover  || '',
    ytid   : el.dataset.ytid   || '',
  };
}

function playCard(cardEl)     { const t = getTrackData(cardEl); loadTrack(t); if (lyricsOpen) renderLyrics(t); }
function playCardWide(cardEl) { const t = getTrackData(cardEl); loadTrack(t); if (lyricsOpen) renderLyrics(t); }
function playRow(rowEl) {
  const t = getTrackData(rowEl);
  loadTrack(t);
  if (lyricsOpen) renderLyrics(t);
  document.querySelectorAll('.track-row').forEach(r => r.classList.remove('playing'));
  rowEl.classList.add('playing');
}

function loadTrack(t) {
  currentTrack = t;
  document.getElementById('playerTitle').textContent  = t.title;
  document.getElementById('playerArtist').textContent = t.artist;

  // ── Thumbnail — image only, no emoji ──
  // cover is already set to YT thumbnail as fallback in PHP JSON, but also handle dataset.ytid
  const ytid = t.ytid || (t.src.startsWith('yt:') ? t.src.replace('yt:','') : '');
  setPlayerThumb(t.cover, ytid);

  stopAll();
  document.getElementById('progressFill').style.width = '0%';
  document.getElementById('currentTime').textContent  = '0:00';
  document.getElementById('totalTime').textContent    = t.dur || '—';

  if (t.src.startsWith('yt:')) {
    currentType = 'yt';
    const vid = t.src.replace('yt:', '');
    if (ytReady && ytPlayer) ytPlayer.loadVideoById(vid);
    else ytPending = vid;
  } else if (t.src) {
    currentType = 'audio';
    audio = new Audio(t.src);
    audio.volume = parseFloat(document.getElementById('volFill').style.width) / 100 || 0.7;
    audio.addEventListener('timeupdate', updateProgress);
    audio.addEventListener('ended', () => { playing = false; updatePlayBtn(); nextTrack(); });
    audio.play().catch(() => {});
  } else {
    currentType = null;
  }

  playing = true;
  updatePlayBtn();
  document.getElementById('spPlayer').classList.add('playing');

  const heart = document.getElementById('playerHeart');
  heart.classList.toggle('liked', likedIds.has(t.id));
  heart.textContent = likedIds.has(t.id) ? '♥' : '♡';

  const qi = queue.findIndex(q => q.id === t.id);
  if (qi > -1) trackIdx = qi;

  fetch('api/play.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({id: t.id})
  }).catch(() => {});
}

function stopAll() {
  if (audio) { audio.pause(); audio.src = ''; audio = null; }
  if (ytReady && ytPlayer) { try { ytPlayer.stopVideo(); } catch(e) {} }
  clearInterval(ytTimer);
}

function togglePlay() {
  playing = !playing;
  updatePlayBtn();
  if (currentType === 'yt' && ytReady && ytPlayer) {
    playing ? ytPlayer.playVideo() : ytPlayer.pauseVideo();
  } else if (currentType === 'audio' && audio) {
    playing ? audio.play() : audio.pause();
  }
  document.getElementById('spPlayer').classList.toggle('playing', playing);
}

function updatePlayBtn() {
  document.getElementById('playBtn').textContent = playing ? '⏸' : '▶';
}

function prevTrack() {
  trackIdx = (trackIdx - 1 + queue.length) % queue.length;
  loadTrack(queue[trackIdx]);
}

function nextTrack() {
  trackIdx = (trackIdx + 1) % queue.length;
  loadTrack(queue[trackIdx]);
}

function playAll() {
  queue = [...allTracks]; trackIdx = 0;
  loadTrack(queue[0]);
}

function shuffleAll() {
  queue = [...allTracks].sort(() => Math.random() - .5); trackIdx = 0;
  loadTrack(queue[0]);
}

// ══════════════════════════════
//  PROGRESS & VOLUME
// ══════════════════════════════
function updateProgress() {
  if (!audio) return;
  const pct = (audio.currentTime / audio.duration) * 100 || 0;
  document.getElementById('progressFill').style.width = pct + '%';
  document.getElementById('currentTime').textContent = fmtTime(audio.currentTime);
  if (audio.duration) document.getElementById('totalTime').textContent = fmtTime(audio.duration);
}

function seekTo(e) {
  const bar = document.getElementById('progressBar');
  const pct = e.offsetX / bar.offsetWidth;
  if (currentType === 'audio' && audio && audio.duration) {
    audio.currentTime = pct * audio.duration;
  } else if (currentType === 'yt' && ytReady && ytPlayer) {
    ytPlayer.seekTo(pct * ytPlayer.getDuration(), true);
  }
}

function fmtTime(s) {
  if (!s || isNaN(s)) return '0:00';
  return `${Math.floor(s/60)}:${String(Math.floor(s%60)).padStart(2,'0')}`;
}

function setVolume(e) {
  const bar = document.getElementById('volFill').parentElement;
  const pct = Math.max(0, Math.min(1, e.offsetX / bar.offsetWidth));
  document.getElementById('volFill').style.width = (pct * 100) + '%';
  if (audio) audio.volume = pct;
  if (ytReady && ytPlayer) ytPlayer.setVolume(pct * 100);
}

// ══════════════════════════════
//  LIKES
// ══════════════════════════════
function toggleLike(btn, id) {
  if (likedIds.has(id)) {
    likedIds.delete(id); btn.textContent = '♡'; btn.classList.remove('liked');
  } else {
    likedIds.add(id); btn.textContent = '♥'; btn.classList.add('liked');
  }
  localStorage.setItem('liked', JSON.stringify([...likedIds]));
}

function togglePlayerLike() {
  if (!currentTrack) return;
  const btn = document.getElementById('playerHeart');
  toggleLike(btn, currentTrack.id);
}

// ══════════════════════════════
//  LYRICS
// ══════════════════════════════
function openLyrics(cardEl)    { const t = getTrackData(cardEl); renderLyrics(t); showLyricsPanel(); }
function openLyricsRow(rowEl)  { const t = getTrackData(rowEl);  renderLyrics(t); showLyricsPanel(); }

function renderLyrics(t) {
  document.getElementById('lyricsTitle').textContent  = t.title;
  document.getElementById('lyricsArtist').textContent = t.artist;
  const thumb = document.getElementById('lyricsThumb');
  const ytid  = t.ytid || (t.src && t.src.startsWith('yt:') ? t.src.replace('yt:','') : '');
  const img   = t.cover || (ytid ? 'https://img.youtube.com/vi/' + ytid + '/mqdefault.jpg' : '');
  thumb.innerHTML = img
    ? `<img src="${img}" style="width:100%;height:100%;object-fit:cover;border-radius:8px">`
    : `<div style="width:100%;height:100%;background:linear-gradient(135deg,#1a0533,#0d1b3e)"></div>`;
  const box = document.getElementById('lyricsText');
  if (t.lyrics) {
    box.innerHTML = t.lyrics.split('\n').map((l, i) =>
      `<span class="ly-line${i===0?' active':''}">${l||'&nbsp;'}</span>`
    ).join('');
  } else {
    box.innerHTML = `<div class="sp-lyrics-empty"><span>🎤</span>No lyrics available for this track.</div>`;
  }
}

function showLyricsPanel() {
  document.getElementById('lyricsPanel').classList.add('open');
  document.getElementById('lyricsToggleBtn').classList.add('active');
  lyricsOpen = true;
}
function closeLyrics() {
  document.getElementById('lyricsPanel').classList.remove('open');
  document.getElementById('lyricsToggleBtn').classList.remove('active');
  lyricsOpen = false;
}
function toggleLyricsPanel() {
  if (lyricsOpen) { closeLyrics(); return; }
  if (currentTrack) renderLyrics(currentTrack);
  showLyricsPanel();
}

// ══════════════════════════════
//  SEARCH
// ══════════════════════════════
document.getElementById('searchInput').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.track-row').forEach(row => {
    row.style.display = (row.dataset.title + row.dataset.artist).toLowerCase().includes(q) ? '' : 'none';
  });
  if (q) switchTab('all', document.querySelectorAll('.sp-tab')[3]);
});

// ══════════════════════════════
//  YOUTUBE IFRAME API
// ══════════════════════════════
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
        if (ytPending) { ytPlayer.loadVideoById(ytPending); ytPending = null; }
      },
      onStateChange: e => {
        if (e.data === YT.PlayerState.ENDED) { playing = false; updatePlayBtn(); nextTrack(); }
        if (e.data === YT.PlayerState.PLAYING) {
          clearInterval(ytTimer);
          ytTimer = setInterval(() => {
            const cur = ytPlayer.getCurrentTime() || 0;
            const dur = ytPlayer.getDuration()    || 0;
            if (dur > 0) {
              document.getElementById('progressFill').style.width = (cur/dur*100) + '%';
              document.getElementById('currentTime').textContent  = fmtTime(cur);
              document.getElementById('totalTime').textContent    = fmtTime(dur);
            }
          }, 500);
        }
        if (e.data === YT.PlayerState.PAUSED) clearInterval(ytTimer);
      }
    }
  });
}
</script>
</body>
</html>