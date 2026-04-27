<?php
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/config.php';

// ── Create settings table if not exists (MySQL) ──
db()->exec("
    CREATE TABLE IF NOT EXISTS site_settings (
        setting_key  VARCHAR(120) PRIMARY KEY,
        setting_val  TEXT,
        updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// ── Save setting (MySQL upsert) ──
function saveSetting(string $key, string $value): void {
    $s = db()->prepare("
        INSERT INTO site_settings (setting_key, setting_val, updated_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val), updated_at = NOW()
    ");
    $s->execute([$key, $value]);
}

// ── v(): escaped output helper ──
function v(string $key, array $defaults): string {
    return htmlspecialchars(setting($key, $defaults[$key] ?? ''), ENT_QUOTES, 'UTF-8');
}

// ── Defaults ──
$defaults = [
    // Identity
    'logo_text'              => 'RCL',
    'logo_dot_color'         => '#e50914',
    'site_tagline'           => 'Music. Blogs. Streams.',
    // Hero copy
    'hero_title_line1'       => 'Feel Every Beat.',
    'hero_title_line2'       => 'Live Every Story.',
    'hero_desc'              => 'Songs, stories, and streams — all in one place.',
    // CTA copy
    'cta_title_line1'        => 'Join the',
    'cta_title_line2'        => 'RCL Community.',
    'cta_sub'                => 'New drops, behind-the-scenes content, and early access to live streams — straight to your inbox.',
    // Footer copy
    'footer_tagline'         => 'Music. Blogs. Streams.<br>All from the heart.',
    // Socials
    'social_youtube'         => 'https://youtube.com/@9xbloc959',
    'social_spotify'         => '#',
    'social_instagram'       => '#',
    'social_tiktok'          => '#',
    'social_youtube_label'   => 'YT',
    'social_spotify_label'   => 'SP',
    'social_instagram_label' => 'IG',
    'social_tiktok_label'    => 'TK',
    // Theme
    'active_theme'           => 'dark',
    // Dark palette
    'dark_bg'                => '#141414',
    'dark_bg2'               => '#1a1a1a',
    'dark_bg3'               => '#000000',
    'dark_card'              => '#1f1f1f',
    'dark_text'              => '#ffffff',
    'dark_muted'             => '#b3b3b3',
    'dark_accent'            => '#e50914',
    'dark_accent_dk'         => '#b20710',
    'dark_accent_lt'         => '#ff2b39',
    // Light palette
    'light_bg'               => '#f5f5f5',
    'light_bg2'              => '#ebebeb',
    'light_bg3'              => '#ffffff',
    'light_card'             => '#ffffff',
    'light_text'             => '#0d0d0d',
    'light_muted'            => '#666666',
    'light_accent'           => '#e50914',
    'light_accent_dk'        => '#b20710',
    'light_accent_lt'        => '#ff2b39',
    // Typography
    'font_main'              => 'Inter',
    'font_custom_url'        => '',
    // Logo image
    'logo_image'             => '',
    // ── Component palette — Nav ──────────────────────────────────────────
    'comp_nav_bg'            => '#000000',
    'comp_nav_bg_scrolled'   => '#1a1a1a',
    'comp_nav_text'          => '#ffffff',
    'comp_nav_muted'         => '#b3b3b3',
    'comp_nav_accent'        => '#e50914',
    'comp_nav_border'        => '#1f1f1f',
    'comp_nav_mobile_bg'     => '#000000',
    // ── Component palette — Mini Player ─────────────────────────────────
    'comp_player_bg'         => '#1f1f1f',
    'comp_player_border'     => '#333333',
    'comp_player_text'       => '#ffffff',
    'comp_player_muted'      => '#b3b3b3',
    'comp_player_accent'     => '#e50914',
    'comp_player_bar_bg'     => '#333333',
    'comp_player_shadow'     => '#000000',
    // ── Component palette — Footer ───────────────────────────────────────
    'comp_footer_bg'         => '#000000',
    'comp_footer_border'     => '#1f1f1f',
    'comp_footer_text'       => '#ffffff',
    'comp_footer_muted'      => '#b3b3b3',
    'comp_footer_accent'     => '#e50914',
    'comp_footer_link'       => '#555555',
    // ── Component palette — Hero ─────────────────────────────────────────
    'comp_hero_text'         => '#ffffff',
    'comp_hero_desc'         => '#b3b3b3',
    'comp_hero_stat_label'   => '#888888',
    'comp_hero_divider'      => '#333333',
    'comp_hero_scroll'       => '#888888',
    'comp_hero_accent'       => '#e50914',
    // ── Component palette — Ticker ───────────────────────────────────────
    'comp_ticker_bg'         => '#e50914',
    'comp_ticker_text'       => '#ffffff',
    'comp_ticker_sep'        => '#bb0000',
];

// ── Handle POST ──
$saved  = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'logo_text', 'logo_dot_color', 'site_tagline', 'hero_title_line1', 'hero_title_line2',
        'hero_desc', 'cta_title_line1', 'cta_title_line2', 'cta_sub', 'footer_tagline',
        'social_youtube', 'social_spotify', 'social_instagram', 'social_tiktok',
        'social_youtube_label', 'social_spotify_label', 'social_instagram_label', 'social_tiktok_label',
        'active_theme',
        'light_bg', 'light_bg2', 'light_bg3', 'light_card', 'light_text', 'light_muted',
        'light_accent', 'light_accent_dk', 'light_accent_lt',
        'dark_bg', 'dark_bg2', 'dark_bg3', 'dark_card', 'dark_text', 'dark_muted',
        'dark_accent', 'dark_accent_dk', 'dark_accent_lt',
        'font_main', 'font_custom_url',
        // Component palette fields
        'comp_nav_bg', 'comp_nav_bg_scrolled', 'comp_nav_text', 'comp_nav_muted',
        'comp_nav_accent', 'comp_nav_border', 'comp_nav_mobile_bg',
        'comp_player_bg', 'comp_player_border', 'comp_player_text', 'comp_player_muted',
        'comp_player_accent', 'comp_player_bar_bg', 'comp_player_shadow',
        'comp_footer_bg', 'comp_footer_border', 'comp_footer_text', 'comp_footer_muted',
        'comp_footer_accent', 'comp_footer_link',
        'comp_hero_text', 'comp_hero_desc', 'comp_hero_stat_label',
        'comp_hero_divider', 'comp_hero_scroll', 'comp_hero_accent',
        'comp_ticker_bg', 'comp_ticker_text', 'comp_ticker_sep',
    ];

    foreach ($fields as $f) {
        saveSetting($f, trim($_POST[$f] ?? ''));
    }

    // Handle logo image upload
    if (!empty($_FILES['logo_image']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['logo_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];
        if (!in_array($ext, $allowed)) {
            $errors[] = 'Logo image must be PNG, JPG, GIF, SVG, or WEBP.';
        } else {
            $dir = __DIR__ . '/images/';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $fname = 'logo.' . $ext;
            if (move_uploaded_file($_FILES['logo_image']['tmp_name'], $dir . $fname)) {
                saveSetting('logo_image', 'images/' . $fname);
            } else {
                $errors[] = 'Failed to upload logo image.';
            }
        }
    }

    if (empty($errors)) $saved = true;
}

$activeTheme = setting('active_theme', 'dark');
$currentFont = setting('font_main',    'Inter');
$logoImage   = setting('logo_image',   '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Appearance — RCL Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════
   APPEARANCE ADMIN — RCL
   Aesthetic: Editorial + Brutalist Dark
══════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:       #0c0c0c;
  --surface:  #141414;
  --surface2: #1c1c1c;
  --border:   rgba(255,255,255,0.08);
  --border2:  rgba(255,255,255,0.16);
  --accent:   #e50914;
  --accent-lt:#ff3344;
  --text:     #f0f0f0;
  --muted:    #888;
  --dim:      rgba(255,255,255,0.04);
  --green:    #22c55e;
  --ff:       'Syne', sans-serif;
  --ff-mono:  'DM Mono', monospace;
  --r:        6px;
  --t:        0.2s;
}

html { scroll-behavior: smooth; }
body {
  font-family: var(--ff);
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  -webkit-font-smoothing: antialiased;
}

.shell { display: flex; min-height: 100vh; }

/* ── SIDEBAR ── */
.sidebar {
  width: 220px; flex-shrink: 0;
  background: var(--surface); border-right: 1px solid var(--border);
  display: flex; flex-direction: column;
  position: sticky; top: 0; height: 100vh; overflow-y: auto;
}
.sidebar-logo { padding: 24px 20px 20px; border-bottom: 1px solid var(--border); }
.sidebar-logo .name { font-size: 1.4rem; font-weight: 800; color: var(--accent); letter-spacing: -0.03em; line-height: 1; }
.sidebar-logo .name span { color: var(--text); }
.sidebar-logo .sub { font-size: 0.6rem; font-weight: 600; letter-spacing: 0.14em; text-transform: uppercase; color: var(--muted); margin-top: 4px; font-family: var(--ff-mono); }
.sidebar-nav { padding: 16px 12px; flex: 1; }
.sidebar-nav a { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: var(--r); font-size: 0.78rem; font-weight: 700; letter-spacing: 0.04em; color: var(--muted); text-decoration: none; transition: color var(--t), background var(--t); margin-bottom: 2px; }
.sidebar-nav a:hover { color: var(--text); background: var(--dim); }
.sidebar-nav a.active { color: var(--text); background: rgba(229,9,20,0.12); }
.sidebar-nav a .icon { font-size: 0.85rem; width: 18px; text-align: center; }
.sidebar-divider { height: 1px; background: var(--border); margin: 12px 0; }
.sidebar-back { padding: 16px 12px; border-top: 1px solid var(--border); }
.sidebar-back a { display: flex; align-items: center; gap: 8px; font-size: 0.72rem; font-weight: 600; color: var(--muted); text-decoration: none; transition: color var(--t); letter-spacing: 0.04em; }
.sidebar-back a:hover { color: var(--text); }

/* ── MAIN ── */
.main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.topbar { background: var(--surface); border-bottom: 1px solid var(--border); padding: 18px 32px; display: flex; align-items: center; justify-content: space-between; gap: 16px; position: sticky; top: 0; z-index: 100; }
.topbar-title { font-size: 1.1rem; font-weight: 800; letter-spacing: -0.02em; color: var(--text); }
.topbar-title span { color: var(--muted); font-weight: 600; font-size: 0.85rem; margin-left: 10px; }
.topbar-actions { display: flex; gap: 10px; align-items: center; }

.btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px; border-radius: var(--r); font-family: var(--ff); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; cursor: pointer; border: none; transition: background var(--t); text-decoration: none; white-space: nowrap; }
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:hover { background: var(--accent-lt); }
.btn-ghost { background: var(--dim); color: var(--muted); border: 1px solid var(--border2); }
.btn-ghost:hover { color: var(--text); background: rgba(255,255,255,0.08); }

.content { padding: 28px 32px; flex: 1; }

/* ── TOAST ── */
.toast { display: flex; align-items: center; gap: 10px; background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.3); color: var(--green); padding: 12px 18px; border-radius: var(--r); font-size: 0.8rem; font-weight: 600; margin-bottom: 24px; animation: slide-in 0.3s ease; }
.toast-err { background: rgba(229,9,20,0.1); border-color: rgba(229,9,20,0.3); color: #ff6b6b; }
@keyframes slide-in { from{ opacity:0; transform:translateY(-8px); } to{ opacity:1; transform:translateY(0); } }

/* ── PANELS ── */
.panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r); margin-bottom: 20px; overflow: hidden; }
.panel-head { padding: 16px 22px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 12px; cursor: pointer; user-select: none; transition: background var(--t); }
.panel-head:hover { background: var(--dim); }
.panel-head .ph-icon { width: 32px; height: 32px; border-radius: 8px; background: rgba(229,9,20,0.12); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0; }
.panel-head .ph-title { font-size: 0.88rem; font-weight: 700; color: var(--text); letter-spacing: 0.01em; flex: 1; }
.panel-head .ph-sub { font-size: 0.65rem; color: var(--muted); font-family: var(--ff-mono); }
.panel-head .ph-arrow { color: var(--muted); font-size: 0.7rem; transition: transform var(--t); }
.panel-head.open .ph-arrow { transform: rotate(180deg); }
.panel-body { display: none; padding: 22px; }
.panel-body.open { display: block; }

/* ── FORM ── */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-grid-3 { grid-template-columns: 1fr 1fr 1fr; }
.form-grid-4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
.form-grid-full { grid-column: 1 / -1; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field label { font-size: 0.63rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted); font-family: var(--ff-mono); }
.field input[type="text"],
.field input[type="url"],
.field textarea,
.field select { width: 100%; background: var(--surface2); border: 1px solid var(--border2); border-radius: 5px; color: var(--text); font-family: var(--ff-mono); font-size: 0.82rem; padding: 10px 13px; outline: none; transition: border-color var(--t); resize: vertical; }
.field input:focus, .field textarea:focus, .field select:focus { border-color: var(--accent); }
.field textarea { min-height: 80px; }

/* ── COLOR PICKER ── */
.color-field { display: flex; align-items: center; gap: 8px; background: var(--surface2); border: 1px solid var(--border2); border-radius: 5px; padding: 6px 10px; transition: border-color var(--t); }
.color-field:focus-within { border-color: var(--accent); }
.color-field input[type="color"] { width: 28px; height: 28px; border: none; background: transparent; cursor: pointer; border-radius: 4px; padding: 0; flex-shrink: 0; }
.color-field input[type="text"] { flex: 1; background: transparent; border: none !important; color: var(--text); font-family: var(--ff-mono); font-size: 0.82rem; padding: 0 !important; outline: none; min-width: 0; }

/* ── LOGO PREVIEW ── */
.logo-preview { display: flex; align-items: center; gap: 16px; background: var(--surface2); border: 1px solid var(--border); border-radius: var(--r); padding: 20px; margin-bottom: 16px; }
.logo-preview-img { width: 60px; height: 60px; border-radius: 8px; background: #111; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; overflow: hidden; }
.logo-preview-img img { width: 100%; height: 100%; object-fit: contain; }
.logo-preview-text { font-size: 2rem; font-weight: 800; color: var(--accent); letter-spacing: -0.03em; line-height: 1; }
.logo-preview-text span { color: var(--text); }
.logo-preview-meta { font-size: 0.7rem; color: var(--muted); margin-top: 4px; font-family: var(--ff-mono); }

/* ── UPLOAD ── */
.upload-zone { border: 2px dashed var(--border2); border-radius: var(--r); padding: 20px; text-align: center; cursor: pointer; transition: border-color var(--t), background var(--t); position: relative; }
.upload-zone:hover { border-color: var(--accent); background: rgba(229,9,20,0.04); }
.upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
.upload-icon { font-size: 1.4rem; margin-bottom: 6px; }
.upload-label { font-size: 0.75rem; font-weight: 600; color: var(--text); margin-bottom: 2px; }
.upload-sub { font-size: 0.62rem; color: var(--muted); font-family: var(--ff-mono); }

/* ── THEME CARDS ── */
.theme-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
.theme-card { border: 2px solid var(--border2); border-radius: var(--r); padding: 16px; cursor: pointer; transition: border-color var(--t); position: relative; }
.theme-card.selected { border-color: var(--accent); }
.theme-card input[type="radio"] { position: absolute; opacity: 0; pointer-events: none; }
.theme-card-preview { border-radius: 5px; height: 64px; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 4px; overflow: hidden; }
.tc-dot { width: 8px; height: 8px; border-radius: 50%; }
.theme-card-label { font-size: 0.75rem; font-weight: 700; color: var(--text); letter-spacing: 0.04em; }
.theme-card-sub { font-size: 0.62rem; color: var(--muted); font-family: var(--ff-mono); margin-top: 2px; }
.theme-active-badge { position: absolute; top: 10px; right: 10px; background: var(--accent); color: #fff; font-size: 0.55rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; padding: 2px 7px; border-radius: 2px; }

/* ── PALETTE ── */
.palette-section { margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border); }
.palette-section:first-child { margin-top: 0; padding-top: 0; border-top: none; }
.palette-section-title { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--muted); margin-bottom: 14px; font-family: var(--ff-mono); display: flex; align-items: center; gap: 8px; }
.palette-section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* ── COMPONENT PREVIEW STRIP ── */
.comp-preview-strip {
  display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px;
  margin-bottom: 22px; padding: 14px; border-radius: 8px;
  background: #080808; border: 1px solid var(--border);
}
.comp-swatch { border-radius: 6px; padding: 10px 8px; text-align: center; border: 1px solid rgba(255,255,255,0.06); }
.comp-swatch-bar { height: 28px; border-radius: 4px; margin-bottom: 6px; }
.comp-swatch-label { font-size: 0.55rem; font-family: var(--ff-mono); color: var(--muted); letter-spacing: 0.04em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── COMPONENT TABS ── */
.comp-tabs { display: flex; gap: 2px; background: var(--surface2); border-radius: 6px; padding: 3px; margin-bottom: 20px; flex-wrap: wrap; }
.comp-tab { padding: 7px 14px; border-radius: 4px; font-family: var(--ff); font-size: 0.68rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; background: none; border: none; color: var(--muted); cursor: pointer; transition: color var(--t), background var(--t); display: flex; align-items: center; gap: 6px; white-space: nowrap; }
.comp-tab .ct-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; opacity: 0.5; }
.comp-tab.active { background: var(--surface); color: var(--text); }
.comp-tab.active .ct-dot { opacity: 1; }
.comp-content { display: none; }
.comp-content.active { display: block; }

/* ── SOCIAL ROW ── */
.social-row { display: grid; grid-template-columns: 140px 1fr 2fr; gap: 10px; align-items: end; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--border); }
.social-row:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.social-platform { display: flex; align-items: center; gap: 8px; font-size: 0.72rem; font-weight: 700; color: var(--muted); letter-spacing: 0.04em; padding-bottom: 10px; }
.social-platform .sp-icon { width: 28px; height: 28px; border-radius: 6px; background: var(--dim); display: flex; align-items: center; justify-content: center; font-size: 0.7rem; }

/* ── FONTS ── */
.font-presets { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 16px; }
.font-preset { background: var(--surface2); border: 2px solid var(--border2); border-radius: 5px; padding: 12px 8px; text-align: center; cursor: pointer; transition: border-color var(--t), background var(--t); }
.font-preset:hover { background: rgba(255,255,255,0.05); }
.font-preset.active { border-color: var(--accent); background: rgba(229,9,20,0.06); }
.font-preset .fp-name { font-size: 0.6rem; color: var(--muted); font-family: var(--ff-mono); margin-top: 4px; }
.font-preset .fp-sample { font-size: 1.1rem; font-weight: 700; color: var(--text); line-height: 1.2; }

/* ── PREVIEW BAR ── */
.preview-bar { position: fixed; bottom: 0; left: 220px; right: 0; background: rgba(20,20,20,0.97); border-top: 1px solid var(--border); padding: 12px 32px; display: flex; align-items: center; justify-content: space-between; gap: 16px; z-index: 200; backdrop-filter: blur(10px); }
.preview-bar-left { display: flex; align-items: center; gap: 10px; }
.preview-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--green); animation: pulse-dot 2s ease-in-out infinite; }
@keyframes pulse-dot { 0%,100%{ opacity:1; } 50%{ opacity:0.4; } }
.preview-label { font-size: 0.7rem; font-weight: 600; color: var(--muted); font-family: var(--ff-mono); }

/* ── TABS (palette) ── */
.tabs { display: flex; gap: 2px; background: var(--surface2); border-radius: 6px; padding: 3px; margin-bottom: 20px; width: fit-content; }
.tab-btn { padding: 7px 16px; border-radius: 4px; font-family: var(--ff); font-size: 0.7rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; background: none; border: none; color: var(--muted); cursor: pointer; transition: color var(--t), background var(--t); }
.tab-btn.active { background: var(--surface); color: var(--text); }
.tab-content { display: none; }
.tab-content.active { display: block; }

/* ── INFO CALLOUT ── */
.callout { background: rgba(229,9,20,0.06); border: 1px solid rgba(229,9,20,0.18); border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; font-size: 0.72rem; color: #ff9aa2; font-family: var(--ff-mono); line-height: 1.6; }
.callout strong { color: var(--accent); }

.divider { height: 1px; background: var(--border); margin: 20px 0; }
.hint { font-size: 0.62rem; color: var(--muted); font-family: var(--ff-mono); margin-top: 4px; line-height: 1.5; }
.spacer { height: 80px; }

@media (max-width: 900px) {
  .sidebar { display: none; }
  .preview-bar { left: 0; }
  .form-grid { grid-template-columns: 1fr; }
  .form-grid-3, .form-grid-4 { grid-template-columns: 1fr 1fr; }
  .font-presets { grid-template-columns: repeat(2, 1fr); }
  .theme-cards { grid-template-columns: 1fr; }
  .social-row { grid-template-columns: 1fr 1fr; }
  .social-row .social-platform { grid-column: 1 / -1; padding-bottom: 0; }
  .content { padding: 20px 16px; }
  .topbar { padding: 14px 16px; }
  .comp-preview-strip { grid-template-columns: repeat(3, 1fr); }
}
</style>
</head>
<body>

<div class="shell">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="name">RCL<span>.</span></div>
      <div class="sub">Admin Panel</div>
    </div>
    <nav class="sidebar-nav">
      <a href="#identity"   class="active"><span class="icon">✦</span> Identity</a>
      <a href="#theme"                    ><span class="icon">◐</span> Theme</a>
      <a href="#palette"                  ><span class="icon">◈</span> Palette</a>
      <a href="#components"               ><span class="icon">⬡</span> Components</a>
      <a href="#typography"               ><span class="icon">T</span> Typography</a>
      <a href="#socials"                  ><span class="icon">⇡</span> Socials</a>
      <a href="#copy"                     ><span class="icon">✎</span> Text &amp; Copy</a>
      <div class="sidebar-divider"></div>
      <a href="index.php"  ><span class="icon">⌂</span> Dashboard</a>
      <a href="music.php"  ><span class="icon">♪</span> Music</a>
      <a href="videos.php" ><span class="icon">▶</span> Videos</a>
      <a href="blog.php"   ><span class="icon">✐</span> Blog</a>
    </nav>
    <div class="sidebar-back">
      <a href="../index.php">← Back to site</a>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="main">
    <div class="topbar">
      <div class="topbar-title">
        Appearance
        <span>Customize your site's look and feel</span>
      </div>
      <div class="topbar-actions">
        <a href="../index.php" class="btn btn-ghost" target="_blank">↗ Preview Site</a>
        <button class="btn btn-primary" form="appearance-form" type="submit">✓ Save Changes</button>
      </div>
    </div>

    <div class="content">

      <?php if ($saved): ?>
      <div class="toast">✓ Appearance settings saved successfully.</div>
      <?php endif; ?>
      <?php foreach ($errors as $e): ?>
      <div class="toast toast-err">⚠ <?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>

      <form id="appearance-form" method="POST" enctype="multipart/form-data">

        <!-- ══ IDENTITY ══ -->
        <div class="panel" id="identity">
          <div class="panel-head open" onclick="togglePanel(this)">
            <div class="ph-icon">✦</div>
            <div>
              <div class="ph-title">Logo &amp; Identity</div>
              <div class="ph-sub">Logo text, image, tagline</div>
            </div>
            <div class="ph-arrow">▼</div>
          </div>
          <div class="panel-body open">
            <div class="logo-preview">
              <div class="logo-preview-img" id="logoImgPreview">
                <?php if ($logoImage): ?>
                  <img src="/<?= htmlspecialchars($logoImage) ?>" alt="Logo">
                <?php else: ?>
                  <span style="font-size:0.6rem;color:var(--muted);font-family:var(--ff-mono);text-align:center">No<br>Image</span>
                <?php endif; ?>
              </div>
              <div>
                <div class="logo-preview-text" id="logoTextPreview">
                  <?= v('logo_text', $defaults) ?><span id="logoDotPreview" style="color:<?= v('logo_dot_color', $defaults) ?>">.</span>
                </div>
                <div class="logo-preview-meta">Live logo preview</div>
              </div>
            </div>

            <div class="form-grid">
              <div class="field">
                <label>Logo Text</label>
                <input type="text" name="logo_text" value="<?= v('logo_text', $defaults) ?>"
                       oninput="document.getElementById('logoTextPreview').childNodes[0].textContent=this.value"
                       placeholder="RCL">
                <div class="hint">Text shown in nav and footer</div>
              </div>
              <div class="field">
                <label>Logo Dot Color</label>
                <div class="color-field">
                  <input type="color" value="<?= v('logo_dot_color', $defaults) ?>"
                         oninput="syncColor(this,'logo_dot_color_text');document.getElementById('logoDotPreview').style.color=this.value">
                  <input type="text" name="logo_dot_color" id="logo_dot_color_text"
                         value="<?= v('logo_dot_color', $defaults) ?>"
                         oninput="syncColorFromText(this)">
                </div>
              </div>
              <div class="field form-grid-full">
                <label>Logo Image <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional — overrides text)</span></label>
                <div class="upload-zone">
                  <input type="file" name="logo_image" accept="image/*" onchange="previewLogo(this)">
                  <div class="upload-icon">📁</div>
                  <div class="upload-label" id="uploadLabel">Click to upload logo image</div>
                  <div class="upload-sub">PNG, SVG, JPG, WEBP · Max 2MB</div>
                </div>
              </div>
              <div class="field">
                <label>Site Tagline</label>
                <input type="text" name="site_tagline" value="<?= v('site_tagline', $defaults) ?>" placeholder="Music. Blogs. Streams.">
                <div class="hint">Shown in footer below logo</div>
              </div>
            </div>
          </div>
        </div>

        <!-- ══ THEME ══ -->
        <div class="panel" id="theme">
          <div class="panel-head open" onclick="togglePanel(this)">
            <div class="ph-icon">◐</div>
            <div>
              <div class="ph-title">Active Theme</div>
              <div class="ph-sub">Light or dark mode</div>
            </div>
            <div class="ph-arrow">▼</div>
          </div>
          <div class="panel-body open">
            <div class="theme-cards">
              <label class="theme-card <?= $activeTheme === 'dark' ? 'selected' : '' ?>" onclick="selectTheme(this)">
                <input type="radio" name="active_theme" value="dark" <?= $activeTheme === 'dark' ? 'checked' : '' ?>>
                <?php if ($activeTheme === 'dark'): ?><span class="theme-active-badge">Active</span><?php endif; ?>
                <div class="theme-card-preview" style="background:#141414">
                  <div class="tc-dot" style="background:#1f1f1f;border:2px solid #333"></div>
                  <div class="tc-dot" style="background:#e50914"></div>
                  <div class="tc-dot" style="background:#fff;opacity:.85"></div>
                </div>
                <div class="theme-card-label">Dark Theme</div>
                <div class="theme-card-sub">Deep black · Netflix style</div>
              </label>
              <label class="theme-card <?= $activeTheme === 'light' ? 'selected' : '' ?>" onclick="selectTheme(this)">
                <input type="radio" name="active_theme" value="light" <?= $activeTheme === 'light' ? 'checked' : '' ?>>
                <?php if ($activeTheme === 'light'): ?><span class="theme-active-badge">Active</span><?php endif; ?>
                <div class="theme-card-preview" style="background:#f5f5f5;border:1px solid #ddd">
                  <div class="tc-dot" style="background:#fff;border:2px solid #ddd"></div>
                  <div class="tc-dot" style="background:#e50914"></div>
                  <div class="tc-dot" style="background:#0d0d0d"></div>
                </div>
                <div class="theme-card-label">Light Theme</div>
                <div class="theme-card-sub">Clean white · editorial</div>
              </label>
            </div>
            <div class="hint">Switch between light and dark. Each has its own palette below.</div>
          </div>
        </div>

        <!-- ══ PALETTE ══ -->
        <div class="panel" id="palette">
          <div class="panel-head open" onclick="togglePanel(this)">
            <div class="ph-icon">◈</div>
            <div>
              <div class="ph-title">Color Palette</div>
              <div class="ph-sub">Page-wide colors for light and dark themes</div>
            </div>
            <div class="ph-arrow">▼</div>
          </div>
          <div class="panel-body open">

            <div class="tabs">
              <button type="button" class="tab-btn active" onclick="switchTab(this,'tab-dark')">Dark Theme</button>
              <button type="button" class="tab-btn"        onclick="switchTab(this,'tab-light')">Light Theme</button>
            </div>

            <!-- DARK -->
            <div class="tab-content active" id="tab-dark">
              <div class="palette-section">
                <div class="palette-section-title">Backgrounds &amp; Text</div>
                <div class="form-grid form-grid-3">
                  <?php
                  $darkColors = [
                    ['dark_bg',    'Main Background',    'Primary page background'],
                    ['dark_bg2',   'Surface Background', 'Cards, alt sections'],
                    ['dark_bg3',   'Deep Background',    'Footer, hero underlay'],
                    ['dark_card',  'Card Background',    'Music/blog cards'],
                    ['dark_text',  'Primary Text',       'Headlines, body text'],
                    ['dark_muted', 'Muted Text',         'Subtitles, captions'],
                  ];
                  foreach ($darkColors as $c):
                    $val = v($c[0], $defaults);
                  ?>
                  <div class="field">
                    <label><?= $c[1] ?></label>
                    <div class="color-field">
                      <input type="color" value="<?= $val ?>" oninput="syncColor(this,'<?= $c[0] ?>_text')">
                      <input type="text" name="<?= $c[0] ?>" id="<?= $c[0] ?>_text" value="<?= $val ?>" oninput="syncColorFromText(this)">
                    </div>
                    <div class="hint"><?= $c[2] ?></div>
                  </div>
                  <?php endforeach; ?>
                </div>
                <div class="palette-section-title" style="margin-top:20px">Accent Colors</div>
                <div class="form-grid form-grid-3">
                  <?php
                  $darkAccents = [
                    ['dark_accent',    'Accent Color', 'Primary brand color (buttons, highlights)'],
                    ['dark_accent_dk', 'Accent Dark',  'Pressed/hover state'],
                    ['dark_accent_lt', 'Accent Light', 'Hover glow state'],
                  ];
                  foreach ($darkAccents as $c):
                    $val = v($c[0], $defaults);
                  ?>
                  <div class="field">
                    <label><?= $c[1] ?></label>
                    <div class="color-field">
                      <input type="color" value="<?= $val ?>" oninput="syncColor(this,'<?= $c[0] ?>_text')">
                      <input type="text" name="<?= $c[0] ?>" id="<?= $c[0] ?>_text" value="<?= $val ?>" oninput="syncColorFromText(this)">
                    </div>
                    <div class="hint"><?= $c[2] ?></div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- LIGHT -->
            <div class="tab-content" id="tab-light">
              <div class="palette-section">
                <div class="palette-section-title">Backgrounds &amp; Text</div>
                <div class="form-grid form-grid-3">
                  <?php
                  $lightColors = [
                    ['light_bg',    'Main Background',    'Primary page background'],
                    ['light_bg2',   'Surface Background', 'Cards, alt sections'],
                    ['light_bg3',   'Deep Background',    'Footer, hero underlay'],
                    ['light_card',  'Card Background',    'Music/blog cards'],
                    ['light_text',  'Primary Text',       'Headlines, body text'],
                    ['light_muted', 'Muted Text',         'Subtitles, captions'],
                  ];
                  foreach ($lightColors as $c):
                    $val = v($c[0], $defaults);
                  ?>
                  <div class="field">
                    <label><?= $c[1] ?></label>
                    <div class="color-field">
                      <input type="color" value="<?= $val ?>" oninput="syncColor(this,'<?= $c[0] ?>_text')">
                      <input type="text" name="<?= $c[0] ?>" id="<?= $c[0] ?>_text" value="<?= $val ?>" oninput="syncColorFromText(this)">
                    </div>
                    <div class="hint"><?= $c[2] ?></div>
                  </div>
                  <?php endforeach; ?>
                </div>
                <div class="palette-section-title" style="margin-top:20px">Accent Colors</div>
                <div class="form-grid form-grid-3">
                  <?php
                  $lightAccents = [
                    ['light_accent',    'Accent Color', 'Primary brand color'],
                    ['light_accent_dk', 'Accent Dark',  'Pressed/hover state'],
                    ['light_accent_lt', 'Accent Light', 'Hover glow state'],
                  ];
                  foreach ($lightAccents as $c):
                    $val = v($c[0], $defaults);
                  ?>
                  <div class="field">
                    <label><?= $c[1] ?></label>
                    <div class="color-field">
                      <input type="color" value="<?= $val ?>" oninput="syncColor(this,'<?= $c[0] ?>_text')">
                      <input type="text" name="<?= $c[0] ?>" id="<?= $c[0] ?>_text" value="<?= $val ?>" oninput="syncColorFromText(this)">
                    </div>
                    <div class="hint"><?= $c[2] ?></div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- ══ COMPONENTS ══ -->
        <div class="panel" id="components">
          <div class="panel-head open" onclick="togglePanel(this)">
            <div class="ph-icon">⬡</div>
            <div>
              <div class="ph-title">Component Palette</div>
              <div class="ph-sub">Nav · Player · Footer · Hero · Ticker — independent of theme</div>
            </div>
            <div class="ph-arrow">▼</div>
          </div>
          <div class="panel-body open">

            <div class="callout">
              <strong>Universal overrides.</strong> These colors apply to specific components regardless of whether your site is in light or dark mode. Change the theme above without worrying about your nav bar or footer changing color unexpectedly.
            </div>

            <!-- Component sub-tabs -->
            <div class="comp-tabs">
              <button type="button" class="comp-tab active" onclick="switchComp(this,'comp-nav')">
                <span class="ct-dot"></span> Nav Bar
              </button>
              <button type="button" class="comp-tab" onclick="switchComp(this,'comp-player')">
                <span class="ct-dot"></span> Music Player
              </button>
              <button type="button" class="comp-tab" onclick="switchComp(this,'comp-footer')">
                <span class="ct-dot"></span> Footer
              </button>
              <button type="button" class="comp-tab" onclick="switchComp(this,'comp-hero')">
                <span class="ct-dot"></span> Hero
              </button>
              <button type="button" class="comp-tab" onclick="switchComp(this,'comp-ticker')">
                <span class="ct-dot"></span> Ticker
              </button>
            </div>

            <!-- ── NAV ── -->
            <div class="comp-content active" id="comp-nav">
              <div class="palette-section">
                <div class="palette-section-title">Navigation Bar</div>
                <div class="form-grid form-grid-3">
                  <?php
                  $navColors = [
                    ['comp_nav_bg',          'Background (top)',     'Transparent gradient start color'],
                    ['comp_nav_bg_scrolled',  'Background (scrolled)','Solid bg when user scrolls down'],
                    ['comp_nav_text',         'Text Color',           'Active links and hamburger icon'],
                    ['comp_nav_muted',        'Muted Text',           'Inactive nav link color'],
                    ['comp_nav_accent',       'Accent',               'Logo color and hover highlights'],
                    ['comp_nav_border',       'Border / Divider',     'Bottom border when scrolled'],
                    ['comp_nav_mobile_bg',    'Mobile Menu BG',       'Background of dropdown on mobile'],
                  ];
                  foreach ($navColors as $c):
                    $val = v($c[0], $defaults);
                  ?>
                  <div class="field">
                    <label><?= $c[1] ?></label>
                    <div class="color-field">
                      <input type="color" value="<?= $val ?>" oninput="syncColor(this,'<?= $c[0] ?>_text')">
                      <input type="text" name="<?= $c[0] ?>" id="<?= $c[0] ?>_text" value="<?= $val ?>" oninput="syncColorFromText(this)">
                    </div>
                    <div class="hint"><?= $c[2] ?></div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- ── PLAYER ── -->
            <div class="comp-content" id="comp-player">
              <div class="palette-section">
                <div class="palette-section-title">Mini Music Player</div>
                <div class="form-grid form-grid-3">
                  <?php
                  $playerColors = [
                    ['comp_player_bg',      'Player Background',  'Background of the floating player bar'],
                    ['comp_player_border',  'Player Border',      'Outline border color'],
                    ['comp_player_text',    'Title Text',         'Song name color'],
                    ['comp_player_muted',   'Muted Text',         'Artist name and control icons'],
                    ['comp_player_accent',  'Accent / Play Btn',  'Play button and progress fill'],
                    ['comp_player_bar_bg',  'Progress Bar Track', 'Empty portion of progress bar'],
                    ['comp_player_shadow',  'Drop Shadow',        'Box shadow color beneath player'],
                  ];
                  foreach ($playerColors as $c):
                    $val = v($c[0], $defaults);
                  ?>
                  <div class="field">
                    <label><?= $c[1] ?></label>
                    <div class="color-field">
                      <input type="color" value="<?= $val ?>" oninput="syncColor(this,'<?= $c[0] ?>_text')">
                      <input type="text" name="<?= $c[0] ?>" id="<?= $c[0] ?>_text" value="<?= $val ?>" oninput="syncColorFromText(this)">
                    </div>
                    <div class="hint"><?= $c[2] ?></div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- ── FOOTER ── -->
            <div class="comp-content" id="comp-footer">
              <div class="palette-section">
                <div class="palette-section-title">Footer</div>
                <div class="form-grid form-grid-3">
                  <?php
                  $footerColors = [
                    ['comp_footer_bg',     'Footer Background', 'Main footer area background'],
                    ['comp_footer_border', 'Top Border',        'Divider line at top of footer'],
                    ['comp_footer_text',   'Logo / Heading',    'Logo text and column headings'],
                    ['comp_footer_muted',  'Tagline / Labels',  'Footer tagline and column headers'],
                    ['comp_footer_accent', 'Accent',            'Logo dot color and social link hover'],
                    ['comp_footer_link',   'Footer Links',      'Default color for footer navigation links'],
                  ];
                  foreach ($footerColors as $c):
                    $val = v($c[0], $defaults);
                  ?>
                  <div class="field">
                    <label><?= $c[1] ?></label>
                    <div class="color-field">
                      <input type="color" value="<?= $val ?>" oninput="syncColor(this,'<?= $c[0] ?>_text')">
                      <input type="text" name="<?= $c[0] ?>" id="<?= $c[0] ?>_text" value="<?= $val ?>" oninput="syncColorFromText(this)">
                    </div>
                    <div class="hint"><?= $c[2] ?></div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- ── HERO ── -->
            <div class="comp-content" id="comp-hero">
              <div class="palette-section">
                <div class="palette-section-title">Hero Section</div>
                <div class="hint" style="margin-bottom:14px">Controls the overlay tint and text colors laid on top of the hero image. The image itself is set separately.</div>
                <div class="form-grid form-grid-3">
                  <?php
                  $heroColors = [
                    ['comp_hero_text',       'Headline Text',       'Hero title color'],
                    ['comp_hero_desc',       'Description Text',    'Hero subtitle / description color'],
                    ['comp_hero_accent',     'Accent',              'Eyebrow rule, em text, scroll line'],
                    ['comp_hero_stat_label', 'Stat Labels',         'Small uppercase stat labels (e.g. "Streams")'],
                    ['comp_hero_divider',    'Stat Divider',        'Vertical divider between hero stats'],
                    ['comp_hero_scroll',     'Scroll Indicator',    'Writing-mode scroll label color'],
                  ];
                  foreach ($heroColors as $c):
                    $val = v($c[0], $defaults);
                  ?>
                  <div class="field">
                    <label><?= $c[1] ?></label>
                    <div class="color-field">
                      <input type="color" value="<?= $val ?>" oninput="syncColor(this,'<?= $c[0] ?>_text')">
                      <input type="text" name="<?= $c[0] ?>" id="<?= $c[0] ?>_text" value="<?= $val ?>" oninput="syncColorFromText(this)">
                    </div>
                    <div class="hint"><?= $c[2] ?></div>
                  </div>
                  <?php endforeach; ?>
                </div>

                <div class="palette-section-title" style="margin-top:20px">Vignette / Overlay</div>
                <div class="hint" style="margin-bottom:14px">These four colors form the gradient vignette darkening the hero image. Use rgba values like <code style="font-family:var(--ff-mono);color:var(--accent)">rgba(0,0,0,0.8)</code> for transparency.</div>
                <div class="form-grid form-grid-4">
                  <?php
                  $vigColors = [
                    ['comp_hero_vignette_l', 'Left Edge',   'Strong side — text sits here'],
                    ['comp_hero_vignette_r', 'Right Edge',  'Fades toward image'],
                    ['comp_hero_vignette_b', 'Bottom Edge', 'Deep black at the very bottom'],
                    ['comp_hero_vignette_t', 'Top Edge',    'Slight darkening at top (nav area)'],
                  ];
                  foreach ($vigColors as $c):
                    $val = v($c[0], $defaults);
                    // For rgba fields, the color picker shows an approximation
                    // We keep a text-only fallback since rgba isn't a hex color
                  ?>
                  <div class="field">
                    <label><?= $c[1] ?></label>
                    <div class="color-field">
                      <input type="color" value="#000000" oninput="syncColor(this,'<?= $c[0] ?>_text')" title="Approximation only — edit hex/rgba in the text field">
                      <input type="text" name="<?= $c[0] ?>" id="<?= $c[0] ?>_text" value="<?= $val ?>" oninput="syncColorFromText(this)" placeholder="rgba(0,0,0,0.8)">
                    </div>
                    <div class="hint"><?= $c[2] ?></div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- ── TICKER ── -->
            <div class="comp-content" id="comp-ticker">
              <div class="palette-section">
                <div class="palette-section-title">Ticker / Announcement Bar</div>
                <div class="form-grid form-grid-3">
                  <?php
                  $tickerColors = [
                    ['comp_ticker_bg',   'Ticker Background', 'The scrolling bar background color'],
                    ['comp_ticker_text', 'Ticker Text',       'Text items inside the ticker'],
                    ['comp_ticker_sep',  'Separator Dots',    'The small dots between ticker items'],
                  ];
                  foreach ($tickerColors as $c):
                    $val = v($c[0], $defaults);
                  ?>
                  <div class="field">
                    <label><?= $c[1] ?></label>
                    <div class="color-field">
                      <input type="color" value="<?= $val ?>" oninput="syncColor(this,'<?= $c[0] ?>_text')">
                      <input type="text" name="<?= $c[0] ?>" id="<?= $c[0] ?>_text" value="<?= $val ?>" oninput="syncColorFromText(this)">
                    </div>
                    <div class="hint"><?= $c[2] ?></div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- ══ TYPOGRAPHY ══ -->
        <div class="panel" id="typography">
          <div class="panel-head open" onclick="togglePanel(this)">
            <div class="ph-icon" style="font-size:1.1rem;font-weight:800">T</div>
            <div>
              <div class="ph-title">Typography</div>
              <div class="ph-sub">Font family selection</div>
            </div>
            <div class="ph-arrow">▼</div>
          </div>
          <div class="panel-body open">
            <div class="font-presets">
              <?php
              $fonts = [
                ['Inter',         'Inter',         'Aa'],
                ['Syne',          'Syne',          'Aa'],
                ['DM+Sans',       'DM Sans',       'Aa'],
                ['Outfit',        'Outfit',        'Aa'],
                ['Space+Grotesk', 'Space Grotesk', 'Aa'],
                ['Raleway',       'Raleway',       'Aa'],
                ['Barlow',        'Barlow',        'Aa'],
                ['Urbanist',      'Urbanist',      'Aa'],
              ];
              foreach ($fonts as $f):
                $active = ($currentFont === $f[1]) ? 'active' : '';
              ?>
              <div class="font-preset <?= $active ?>" onclick="selectFont('<?= $f[1] ?>',this)">
                <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=<?= $f[0] ?>:wght@700&display=swap">
                <div class="fp-sample" style="font-family:'<?= $f[1] ?>'">Aa</div>
                <div class="fp-name"><?= $f[1] ?></div>
              </div>
              <?php endforeach; ?>
            </div>

            <input type="hidden" name="font_main" id="font_main_input" value="<?= v('font_main', $defaults) ?>">

            <div class="divider"></div>
            <div class="field">
              <label>Custom Google Font URL <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
              <input type="url" name="font_custom_url" value="<?= v('font_custom_url', $defaults) ?>"
                     placeholder="https://fonts.googleapis.com/css2?family=YourFont:wght@400;700&display=swap">
              <div class="hint">Paste a Google Fonts embed URL. Leave blank to use a preset above.</div>
            </div>
          </div>
        </div>

        <!-- ══ SOCIALS ══ -->
        <div class="panel" id="socials">
          <div class="panel-head open" onclick="togglePanel(this)">
            <div class="ph-icon">⇡</div>
            <div>
              <div class="ph-title">Social Links</div>
              <div class="ph-sub">URLs and display labels</div>
            </div>
            <div class="ph-arrow">▼</div>
          </div>
          <div class="panel-body open">
            <?php
            $socialPlatforms = [
              ['▶', 'YouTube',   'social_youtube',   'social_youtube_label',   'https://youtube.com/@yourhandle', 'YT'],
              ['●', 'Spotify',   'social_spotify',   'social_spotify_label',   '#', 'SP'],
              ['✦', 'Instagram', 'social_instagram', 'social_instagram_label', '#', 'IG'],
              ['◈', 'TikTok',    'social_tiktok',    'social_tiktok_label',    '#', 'TK'],
            ];
            foreach ($socialPlatforms as $sp):
            ?>
            <div class="social-row">
              <div class="social-platform">
                <div class="sp-icon"><?= $sp[0] ?></div>
                <?= $sp[1] ?>
              </div>
              <div class="field">
                <label>Label</label>
                <input type="text" name="<?= $sp[3] ?>" value="<?= v($sp[3], $defaults) ?>"
                       placeholder="<?= $sp[5] ?>" maxlength="10">
              </div>
              <div class="field">
                <label>URL</label>
                <input type="url" name="<?= $sp[2] ?>" value="<?= v($sp[2], $defaults) ?>"
                       placeholder="<?= $sp[4] ?>">
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- ══ TEXT & COPY ══ -->
        <div class="panel" id="copy">
          <div class="panel-head open" onclick="togglePanel(this)">
            <div class="ph-icon">✎</div>
            <div>
              <div class="ph-title">Text &amp; Copy</div>
              <div class="ph-sub">Hero, CTA, footer text</div>
            </div>
            <div class="ph-arrow">▼</div>
          </div>
          <div class="panel-body open">

            <div class="palette-section-title">Hero Section</div>
            <div class="form-grid">
              <div class="field">
                <label>Hero Title — Line 1</label>
                <input type="text" name="hero_title_line1" value="<?= v('hero_title_line1', $defaults) ?>"
                       placeholder="Feel Every Beat.">
                <div class="hint">Plain text (no italic/accent)</div>
              </div>
              <div class="field">
                <label>Hero Title — Line 2 <span style="color:var(--accent)">italic/accent</span></label>
                <input type="text" name="hero_title_line2" value="<?= v('hero_title_line2', $defaults) ?>"
                       placeholder="Live Every Story.">
                <div class="hint">Displayed in accent color &amp; italic</div>
              </div>
              <div class="field form-grid-full">
                <label>Hero Description</label>
                <textarea name="hero_desc"><?= v('hero_desc', $defaults) ?></textarea>
              </div>
            </div>

            <div class="divider"></div>
            <div class="palette-section-title">CTA / Newsletter Section</div>
            <div class="form-grid">
              <div class="field">
                <label>CTA Title — Line 1</label>
                <input type="text" name="cta_title_line1" value="<?= v('cta_title_line1', $defaults) ?>"
                       placeholder="Join the">
              </div>
              <div class="field">
                <label>CTA Title — Line 2 <span style="color:var(--accent)">accent</span></label>
                <input type="text" name="cta_title_line2" value="<?= v('cta_title_line2', $defaults) ?>"
                       placeholder="RCL Community.">
              </div>
              <div class="field form-grid-full">
                <label>CTA Subtext</label>
                <textarea name="cta_sub"><?= v('cta_sub', $defaults) ?></textarea>
              </div>
            </div>

            <div class="divider"></div>
            <div class="palette-section-title">Footer</div>
            <div class="form-grid">
              <div class="field">
                <label>Footer Tagline</label>
                <textarea name="footer_tagline" style="min-height:60px"><?= v('footer_tagline', $defaults) ?></textarea>
                <div class="hint">HTML allowed (e.g. &lt;br&gt; for line break)</div>
              </div>
            </div>

          </div>
        </div>

        <div class="spacer"></div>
      </form>
    </div>
  </div>
</div>

<!-- PREVIEW BAR -->
<div class="preview-bar">
  <div class="preview-bar-left">
    <div class="preview-dot"></div>
    <span class="preview-label">Changes save to database · Applied on next page load</span>
  </div>
  <div style="display:flex;gap:10px">
    <a href="../index.php" target="_blank" class="btn btn-ghost" style="font-size:0.68rem">↗ Preview</a>
    <button class="btn btn-primary" form="appearance-form" type="submit" style="font-size:0.68rem">✓ Save Changes</button>
  </div>
</div>

<script>
// Panel toggle
function togglePanel(head) {
  head.classList.toggle('open');
  head.nextElementSibling.classList.toggle('open');
}

// Color picker → text field
function syncColor(picker, textId) {
  const t = document.getElementById(textId);
  if (t) t.value = picker.value;
}

// Text field → color picker (sibling)
function syncColorFromText(input) {
  if (/^#[0-9a-fA-F]{6}$/.test(input.value)) {
    const picker = input.previousElementSibling;
    if (picker && picker.type === 'color') picker.value = input.value;
  }
}

// Theme card selection
function selectTheme(card) {
  document.querySelectorAll('.theme-card').forEach(c => {
    c.classList.remove('selected');
    const b = c.querySelector('.theme-active-badge');
    if (b) b.remove();
  });
  card.classList.add('selected');
  const badge = document.createElement('span');
  badge.className = 'theme-active-badge';
  badge.textContent = 'Active';
  card.appendChild(badge);
  const radio = card.querySelector('input[type="radio"]');
  if (radio) radio.checked = true;
}

// Font selection
function selectFont(fontName, el) {
  document.querySelectorAll('.font-preset').forEach(f => f.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('font_main_input').value = fontName;
}

// Tab switching (palette)
function switchTab(btn, tabId) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(tabId).classList.add('active');
}

// Component sub-tab switching
function switchComp(btn, tabId) {
  document.querySelectorAll('.comp-tab').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.comp-content').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(tabId).classList.add('active');
}

// Logo upload preview
function previewLogo(input) {
  if (!input.files[0]) return;
  document.getElementById('uploadLabel').textContent = input.files[0].name;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('logoImgPreview').innerHTML =
      `<img src="${e.target.result}" alt="Logo" style="width:100%;height:100%;object-fit:contain">`;
  };
  reader.readAsDataURL(input.files[0]);
}

// Sidebar scroll highlight
const panels   = document.querySelectorAll('.panel[id]');
const navLinks = document.querySelectorAll('.sidebar-nav a[href^="#"]');
window.addEventListener('scroll', () => {
  let current = '';
  panels.forEach(p => { if (window.scrollY >= p.offsetTop - 120) current = p.id; });
  navLinks.forEach(a => {
    a.classList.toggle('active', a.getAttribute('href') === '#' + current);
  });
}, { passive: true });
</script>
</body>
</html>